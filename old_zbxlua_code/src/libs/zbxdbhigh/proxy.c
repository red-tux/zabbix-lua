/*
** Zabbix
** Copyright (C) 2000-2011 Zabbix SIA
**
** This program is free software; you can redistribute it and/or modify
** it under the terms of the GNU General Public License as published by
** the Free Software Foundation; either version 2 of the License, or
** (at your option) any later version.
**
** This program is distributed in the hope that it will be useful,
** but WITHOUT ANY WARRANTY; without even the implied warranty of
** MERCHANTABILITY or FITNESS FOR A PARTICULAR PURPOSE.  See the
** GNU General Public License for more details.
**
** You should have received a copy of the GNU General Public License
** along with this program; if not, write to the Free Software
** Foundation, Inc., 51 Franklin Street, Fifth Floor, Boston, MA  02110-1301, USA.
**/

#include "common.h"
#include "db.h"
#include "log.h"
#include "sysinfo.h"
#include "zbxserver.h"

#include "proxy.h"
#include "dbcache.h"
#include "discovery.h"

typedef struct
{
	const char		*field;
	const char		*tag;
	zbx_json_type_t		jt;
	char			*default_value;
}
ZBX_HISTORY_FIELD;

typedef struct
{
	const char		*table, *lastfieldname;
	const char		*from, *where;
	ZBX_HISTORY_FIELD	fields[ZBX_MAX_FIELDS];
}
ZBX_HISTORY_TABLE;

static ZBX_HISTORY_TABLE ht = {
	"proxy_history", "history_lastid", "hosts h,items i,",
	"h.hostid=i.hostid and i.itemid=p.itemid and ",
		{
		{"h.host",	ZBX_PROTO_TAG_HOST,		ZBX_JSON_TYPE_STRING,	NULL},
		{"i.key_",	ZBX_PROTO_TAG_KEY,		ZBX_JSON_TYPE_STRING,	NULL},
		{"p.clock",	ZBX_PROTO_TAG_CLOCK,		ZBX_JSON_TYPE_INT,	NULL},
		{"p.ns",	ZBX_PROTO_TAG_NS,		ZBX_JSON_TYPE_INT,	NULL},
		{"p.timestamp",	ZBX_PROTO_TAG_LOGTIMESTAMP,	ZBX_JSON_TYPE_INT,	"0"},
		{"p.source",	ZBX_PROTO_TAG_LOGSOURCE,	ZBX_JSON_TYPE_STRING,	""},
		{"p.severity",	ZBX_PROTO_TAG_LOGSEVERITY,	ZBX_JSON_TYPE_INT,	"0"},
		{"p.value",	ZBX_PROTO_TAG_VALUE,		ZBX_JSON_TYPE_STRING,	NULL},
		{"p.logeventid",ZBX_PROTO_TAG_LOGEVENTID,	ZBX_JSON_TYPE_INT,	"0"},
		{NULL}
		}
};

static ZBX_HISTORY_TABLE dht = {
	"proxy_dhistory", "dhistory_lastid", "", "",
		{
		{"p.clock",	ZBX_PROTO_TAG_CLOCK,		ZBX_JSON_TYPE_INT,	NULL},
		{"p.druleid",	ZBX_PROTO_TAG_DRULE,		ZBX_JSON_TYPE_INT,	NULL},
		{"p.dcheckid",	ZBX_PROTO_TAG_DCHECK,		ZBX_JSON_TYPE_INT,	NULL},
		{"p.type",	ZBX_PROTO_TAG_TYPE,		ZBX_JSON_TYPE_INT,	NULL},
		{"p.ip",	ZBX_PROTO_TAG_IP,		ZBX_JSON_TYPE_STRING,	NULL},
		{"p.dns",	ZBX_PROTO_TAG_DNS,		ZBX_JSON_TYPE_STRING,	NULL},
		{"p.port",	ZBX_PROTO_TAG_PORT,		ZBX_JSON_TYPE_INT,	"0"},
		{"p.key_",	ZBX_PROTO_TAG_KEY,		ZBX_JSON_TYPE_STRING,	""},
		{"p.value",	ZBX_PROTO_TAG_VALUE,		ZBX_JSON_TYPE_STRING,	""},
		{"p.status",	ZBX_PROTO_TAG_STATUS,		ZBX_JSON_TYPE_INT,	"0"},
		{NULL}
		}
};

static ZBX_HISTORY_TABLE areg = {
	"proxy_autoreg_host", "autoreg_host_lastid", "", "",
		{
		{"p.clock",	ZBX_PROTO_TAG_CLOCK,		ZBX_JSON_TYPE_INT,	NULL},
		{"p.host",	ZBX_PROTO_TAG_HOST,		ZBX_JSON_TYPE_STRING,	NULL},
		{"p.listen_ip",	ZBX_PROTO_TAG_IP,		ZBX_JSON_TYPE_STRING,	""},
		{"p.listen_dns",ZBX_PROTO_TAG_DNS,		ZBX_JSON_TYPE_STRING,	""},
		{"p.listen_port",ZBX_PROTO_TAG_PORT,		ZBX_JSON_TYPE_STRING,	"0"},
		{NULL}
		}
};

/******************************************************************************
 *                                                                            *
 * Function: get_proxy_id                                                     *
 *                                                                            *
 * Parameters: host - [IN] require size 'HOST_HOST_LEN_MAX'                   *
 *                                                                            *
 * Return value:  SUCCEED - processed successfully                            *
 *                FAIL - an error occurred                                    *
 *                                                                            *
 * Author: Alexander Vladishev                                                *
 *                                                                            *
 ******************************************************************************/
int	get_proxy_id(struct zbx_json_parse *jp, zbx_uint64_t *hostid, char *host, char *error, int max_error_len)
{
	DB_RESULT	result;
	DB_ROW		row;
	char		*host_esc;
	int		ret = FAIL;

	if (SUCCEED == zbx_json_value_by_name(jp, ZBX_PROTO_TAG_HOST, host, HOST_HOST_LEN_MAX))
	{
		if (FAIL == zbx_check_hostname(host))
		{
			zbx_snprintf(error, max_error_len, "invalid proxy name [%s]", host);
			return ret;
		}

		host_esc = DBdyn_escape_string(host);

		result = DBselect(
				"select hostid"
				" from hosts"
				" where host='%s'"
					" and status in (%d)"
					DB_NODE,
				host_esc, HOST_STATUS_PROXY_ACTIVE, DBnode_local("hostid"));

		zbx_free(host_esc);

		if (NULL != (row = DBfetch(result)) && FAIL == DBis_null(row[0]))
		{
			ZBX_STR2UINT64(*hostid, row[0]);
			ret = SUCCEED;
		}
		else
			zbx_snprintf(error, max_error_len, "proxy [%s] not found", host);

		DBfree_result(result);
	}
	else
		zbx_snprintf(error, max_error_len, "missing name of proxy");

	return ret;
}

/******************************************************************************
 *                                                                            *
 * Function: update_proxy_lastaccess                                          *
 *                                                                            *
 * Author: Alexander Vladishev                                                *
 *                                                                            *
 ******************************************************************************/
void	update_proxy_lastaccess(const zbx_uint64_t hostid)
{
	DBexecute("update hosts set lastaccess=%d where hostid=" ZBX_FS_UI64, time(NULL), hostid);
}

/******************************************************************************
 *                                                                            *
 * Function: get_proxyconfig_table                                            *
 *                                                                            *
 * Purpose: prepare proxy configuration data                                  *
 *                                                                            *
 * Author: Alexander Vladishev                                                *
 *                                                                            *
 ******************************************************************************/
static void	get_proxyconfig_table(zbx_uint64_t proxy_hostid, struct zbx_json *j, const ZBX_TABLE *table,
		zbx_uint64_t *hostids, int hostids_num)
{
	const char	*__function_name = "get_proxyconfig_table";
	char		*sql = NULL;
	size_t		sql_alloc = 4 * ZBX_KIBIBYTE, sql_offset = 0;
	int		f, fld;
	DB_RESULT	result;
	DB_ROW		row;

	zabbix_log(LOG_LEVEL_DEBUG, "In %s() proxy_hostid:" ZBX_FS_UI64 " table:'%s'",
			__function_name, proxy_hostid, table->table);

	zbx_json_addobject(j, table->table);
	zbx_json_addarray(j, "fields");

	sql = zbx_malloc(sql, sql_alloc);

	zbx_snprintf_alloc(&sql, &sql_alloc, &sql_offset, "select t.%s", table->recid);

	zbx_json_addstring(j, NULL, table->recid, ZBX_JSON_TYPE_STRING);

	for (f = 0; 0 != table->fields[f].name; f++)
	{
		if (0 == (table->fields[f].flags & ZBX_PROXY))
			continue;

		zbx_snprintf_alloc(&sql, &sql_alloc, &sql_offset, ",t.%s", table->fields[f].name);

		zbx_json_addstring(j, NULL, table->fields[f].name, ZBX_JSON_TYPE_STRING);
	}

	zbx_json_close(j);	/* fields */

	zbx_json_addarray(j, "data");

	zbx_snprintf_alloc(&sql, &sql_alloc, &sql_offset, " from %s t", table->table);

	if (SUCCEED == str_in_list("globalmacro,regexps,expressions,config", table->table, ','))
	{
		char	*field_name;

		field_name = zbx_dsprintf(NULL, "t.%s", table->recid);
		zbx_snprintf_alloc(&sql, &sql_alloc, &sql_offset, " where 1=1" DB_NODE, DBnode_local(field_name));

		zbx_free(field_name);
	}
	else if (SUCCEED == str_in_list("hosts,interface,hosts_templates,hostmacro", table->table, ','))
	{
		if (0 == hostids_num)
			goto skip_data;

		zbx_strcpy_alloc(&sql, &sql_alloc, &sql_offset, " where");
		DBadd_condition_alloc(&sql, &sql_alloc, &sql_offset, "t.hostid", hostids, hostids_num);
	}
	else if (0 == strcmp(table->table, "items"))
	{
		zbx_snprintf_alloc(&sql, &sql_alloc, &sql_offset,
				",hosts r where t.hostid=r.hostid"
					" and r.proxy_hostid=" ZBX_FS_UI64
					" and r.status in (%d,%d)"
					" and t.status in (%d,%d,%d)"
					" and t.type in (%d,%d,%d,%d,%d,%d,%d,%d,%d,%d,%d,%d,%d,%d,%d)",
				proxy_hostid,
				HOST_STATUS_MONITORED, HOST_STATUS_NOT_MONITORED,
				ITEM_STATUS_ACTIVE, ITEM_STATUS_DISABLED, ITEM_STATUS_NOTSUPPORTED,
				ITEM_TYPE_ZABBIX, ITEM_TYPE_ZABBIX_ACTIVE,
				ITEM_TYPE_SNMPv1, ITEM_TYPE_SNMPv2c, ITEM_TYPE_SNMPv3,
				ITEM_TYPE_IPMI, ITEM_TYPE_TRAPPER, ITEM_TYPE_SIMPLE,
				ITEM_TYPE_HTTPTEST, ITEM_TYPE_EXTERNAL, ITEM_TYPE_DB_MONITOR,
				ITEM_TYPE_SSH, ITEM_TYPE_TELNET, ITEM_TYPE_JMX, ITEM_TYPE_SNMPTRAP);
	}
	else if (0 == strcmp(table->table, "drules"))
	{
		zbx_snprintf_alloc(&sql, &sql_alloc, &sql_offset,
				" where t.proxy_hostid=" ZBX_FS_UI64
					" and t.status=%d",
				proxy_hostid, DRULE_STATUS_MONITORED);
	}
	else if (0 == strcmp(table->table, "dchecks"))
	{
		zbx_snprintf_alloc(&sql, &sql_alloc, &sql_offset,
				",drules r where t.druleid=r.druleid"
					" and r.proxy_hostid=" ZBX_FS_UI64
					" and r.status=%d",
				proxy_hostid, DRULE_STATUS_MONITORED);
	}
	else if (0 == strcmp(table->table, "groups"))
	{
		zbx_snprintf_alloc(&sql, &sql_alloc, &sql_offset,
				",config r where t.groupid=r.discovery_groupid" DB_NODE,
				DBnode_local("r.configid"));
	}

	zbx_snprintf_alloc(&sql, &sql_alloc, &sql_offset, " order by t.%s", table->recid);

	result = DBselect("%s", sql);

	while (NULL != (row = DBfetch(result)))
	{
		fld = 0;
		zbx_json_addarray(j, NULL);
		zbx_json_addstring(j, NULL, row[fld++], ZBX_JSON_TYPE_INT);

		for (f = 0; 0 != table->fields[f].name; f ++)
		{
			if (0 == (table->fields[f].flags & ZBX_PROXY))
				continue;

			switch (table->fields[f].type)
			{
				case ZBX_TYPE_INT:
				case ZBX_TYPE_UINT:
				case ZBX_TYPE_ID:
					zbx_json_addstring(j, NULL, row[fld++], ZBX_JSON_TYPE_INT);
					break;
				default:
					zbx_json_addstring(j, NULL, row[fld++], ZBX_JSON_TYPE_STRING);
					break;
			}
		}
		zbx_json_close(j);
	}
	DBfree_result(result);
skip_data:
	zbx_free(sql);

	zbx_json_close(j);	/* data */
	zbx_json_close(j);	/* table->table */

	zabbix_log(LOG_LEVEL_DEBUG, "End of %s()", __function_name);
}

static void	get_proxy_monitored_hostids(zbx_uint64_t proxy_hostid,
		zbx_uint64_t **hostids, int *hostids_alloc, int *hostids_num)
{
	DB_RESULT	result;
	DB_ROW		row;
	zbx_uint64_t	hostid, *ids = NULL;
	int		ids_alloc = 0, ids_num = 0;
	char		*sql = NULL;
	size_t		sql_alloc = 512, sql_offset;

	sql = zbx_malloc(sql, sql_alloc * sizeof(char));

	result = DBselect(
			"select hostid"
			" from hosts"
			" where proxy_hostid=" ZBX_FS_UI64
				" and status in (%d,%d)",
			proxy_hostid,
			HOST_STATUS_MONITORED, HOST_STATUS_NOT_MONITORED);

	while (NULL != (row = DBfetch(result)))
	{
		ZBX_STR2UINT64(hostid, row[0]);

		uint64_array_add(hostids, hostids_alloc, hostids_num, hostid, 64);
		uint64_array_add(&ids, &ids_alloc, &ids_num, hostid, 64);
	}
	DBfree_result(result);

	while (0 != ids_num)
	{
		sql_offset = 0;
		zbx_strcpy_alloc(&sql, &sql_alloc, &sql_offset,
				"select templateid"
				" from hosts_templates"
				" where");
		DBadd_condition_alloc(&sql, &sql_alloc, &sql_offset, "hostid", ids, ids_num);

		ids_num = 0;

		result = DBselect("%s", sql);

		while (NULL != (row = DBfetch(result)))
		{
			ZBX_STR2UINT64(hostid, row[0]);

			uint64_array_add(hostids, hostids_alloc, hostids_num, hostid, 64);
			uint64_array_add(&ids, &ids_alloc, &ids_num, hostid, 64);
		}
		DBfree_result(result);
	}

	zbx_free(ids);
	zbx_free(sql);
}

/******************************************************************************
 *                                                                            *
 * Function: get_proxyconfig_data                                             *
 *                                                                            *
 * Purpose: prepare proxy configuration data                                  *
 *                                                                            *
 * Author: Alexander Vladishev                                                *
 *                                                                            *
 ******************************************************************************/
void	get_proxyconfig_data(zbx_uint64_t proxy_hostid, struct zbx_json *j)
{
	typedef struct
	{
		const char	*table;
	}
	proxytable_t;

	static const proxytable_t pt[] =
	{
		{"globalmacro"},
		{"hosts"},
		{"interface"},
		{"hosts_templates"},
		{"hostmacro"},
		{"items"},
		{"drules"},
		{"dchecks"},
		{"regexps"},
		{"expressions"},
		{"groups"},
		{"config"},
		{NULL}
	};

	const char	*__function_name = "get_proxyconfig_data";
	int		i;
	const ZBX_TABLE	*table;
	zbx_uint64_t	*hostids = NULL;
	int		hostids_alloc = 0, hostids_num = 0;

	zabbix_log(LOG_LEVEL_DEBUG, "In %s() proxy_hostid:" ZBX_FS_UI64, __function_name, proxy_hostid);

	assert(proxy_hostid);

	get_proxy_monitored_hostids(proxy_hostid, &hostids, &hostids_alloc, &hostids_num);

	for (i = 0; NULL != pt[i].table; i++)
	{
		assert(NULL != (table = DBget_table(pt[i].table)));

		get_proxyconfig_table(proxy_hostid, j, table, hostids, hostids_num);
	}

	zbx_free(hostids);

	zabbix_log(LOG_LEVEL_DEBUG, "End of %s()", __function_name);
}

/******************************************************************************
 *                                                                            *
 * Function: process_proxyconfig_table                                        *
 *                                                                            *
 * Purpose: update configuration table                                        *
 *                                                                            *
 * Return value: SUCCESS - processed successfully                             *
 *               FAIL - an error occurred                                     *
 *                                                                            *
 * Author: Alexander Vladishev                                                *
 *                                                                            *
 ******************************************************************************/
static int	process_proxyconfig_table(struct zbx_json_parse *jp, const char *tablename,
		struct zbx_json_parse *jp_obj, zbx_uint64_t **del, int *del_num)
{
	const char		*__function_name = "process_proxyconfig_table";
	int			f, field_count, insert, is_null, ret = FAIL;
	const ZBX_TABLE		*table = NULL;
	const ZBX_FIELD		*fields[ZBX_MAX_FIELDS];
	struct zbx_json_parse	jp_data, jp_row;
	char			buf[MAX_STRING_LEN], *esc;
	const char		*p, *pf;
	zbx_uint64_t		recid, *new = NULL;
	int			new_alloc = 100, new_num = 0, del_alloc = 100;
	char			*sql = NULL;
	size_t			sql_alloc = 4 * ZBX_KIBIBYTE, sql_offset;
	DB_RESULT		result;
	DB_ROW			row;

	zabbix_log(LOG_LEVEL_DEBUG, "In %s() tablename:'%s'", __function_name, tablename);

	if (NULL == (table = DBget_table(tablename)))
	{
		zabbix_log(LOG_LEVEL_WARNING, "invalid table name \"%s\"", tablename);
		goto exit;
	}

	new = zbx_malloc(new, new_alloc * sizeof(zbx_uint64_t));
	*del = zbx_malloc(*del, del_alloc * sizeof(zbx_uint64_t));
	sql = zbx_malloc(sql, sql_alloc);

	result = DBselect("select %s from %s", table->recid, table->table);
	while (NULL != (row = DBfetch(result)))
	{
		ZBX_STR2UINT64(recid, row[0]);
		uint64_array_add(del, &del_alloc, del_num, recid, 64);
	}
	DBfree_result(result);

	/************************************************************************************/
	/* T1. RECEIVED JSON (jp_obj) DATA FORMAT                                           */
	/************************************************************************************/
	/* Line |                  Data                     | Corresponding structure in DB */
	/* -----+-------------------------------------------+------------------------------ */
	/*   1  | {                                         |                               */
	/*   2  |         "hosts": {                        | first table                   */
	/*   3  |                 "fields": [               | list of table's columns       */
	/*   4  |                         "hostid",         | first column                  */
	/*   5  |                         "host",           | second column                 */
	/*   6  |                         ...               | ...columns                    */
	/*   7  |                 ],                        |                               */
	/*   8  |                 "data": [                 | the table data                */
	/*   9  |                         [                 | first entry                   */
	/*  10  |                               1,          | value for first column        */
	/*  11  |                               "zbx01",    | value for second column       */
	/*  12  |                               ...         | ...values                     */
	/*  13  |                         ],                |                               */
	/*  14  |                         [                 | second entry                  */
	/*  15  |                               2,          | value for first column        */
	/*  16  |                               "zbx02",    | value for second column       */
	/*  17  |                               ...         | ...values                     */
	/*  18  |                         ],                |                               */
	/*  19  |                         ...               | ...entries                    */
	/*  20  |                 ]                         |                               */
	/*  21  |         },                                |                               */
	/*  22  |         "items": {                        | second table                  */
	/*  23  |                 ...                       | ...                           */
	/*  24  |         },                                |                               */
	/*  25  |         ...                               | ...tables                     */
	/*  26  | }                                         |                               */
	/************************************************************************************/

	if (FAIL == zbx_json_brackets_by_name(jp_obj, "fields", &jp_data))	/* get table columns (line 3 in T1) */
		goto json_error;

	p = NULL;
	field_count = 0;
	while (NULL != (p = zbx_json_next_value(&jp_data, p, buf, sizeof(buf), NULL)))	/* iterate column names (lines 4-6 in T1) */
	{
		if (NULL == (fields[field_count] = DBget_field(table, buf)))
		{
			zabbix_log(LOG_LEVEL_WARNING, "invalid field name \"%s\"", buf);
			goto db_error;
		}

		field_count++;
	}

	/* get the entries (line 8 in T1) */
	if (FAIL == zbx_json_brackets_by_name(jp_obj, ZBX_PROTO_TAG_DATA, &jp_data))
		goto json_error;

	/* special preprocessing for 'items' table */
	/* in order to eliminate the conflicts in the 'hostid,key_' unique index */
	if (0 == strcmp(tablename, "items"))
	{
#ifdef HAVE_MYSQL
		if (ZBX_DB_OK > DBexecute("update items set key_=concat('#',itemid)"))
#else
		if (ZBX_DB_OK > DBexecute("update items set key_='#'||itemid"))
#endif
			goto db_error;
	}
	else if (0 == strcmp(tablename, "groups"))
	{
		if (ZBX_DB_OK > DBexecute("delete from config"))
			goto db_error;
	}

	p = NULL;
	sql_offset = 0;

	DBbegin_multiple_update(&sql, &sql_alloc, &sql_offset);

	while (NULL != (p = zbx_json_next(&jp_data, p)))	/* iterate the entries (lines 9, 14 and 19 in T1) */
	{
		if (FAIL == zbx_json_brackets_open(p, &jp_row))
			goto json_error;

		pf = NULL;
		if (NULL == (pf = zbx_json_next_value(&jp_row, pf, buf, sizeof(buf), NULL)))
			goto json_error;

		/* check whether we need to insert a new entry or update an existing */
		ZBX_STR2UINT64(recid, buf);
		insert = (SUCCEED == uint64_array_exists(*del, *del_num, recid) ? 0 : 1);
		uint64_array_add(&new, &new_alloc, &new_num, recid, 64);

		if (0 != insert)
		{
			zbx_snprintf_alloc(&sql, &sql_alloc, &sql_offset, "insert into %s (", table->table);

			for (f = 0; f < field_count; f++)
				zbx_snprintf_alloc(&sql, &sql_alloc, &sql_offset, "%s,", fields[f]->name);

			sql_offset--;
			zbx_snprintf_alloc(&sql, &sql_alloc, &sql_offset, ") values (" ZBX_FS_UI64 ",", recid);
		}
		else if (1 == field_count)	/* only primary key given, no update needed */
		{
			continue;
		}
		else
			zbx_snprintf_alloc(&sql, &sql_alloc, &sql_offset, "update %s set ", table->table);

		f = 1;
		while (NULL != (pf = zbx_json_next_value(&jp_row, pf, buf, sizeof(buf), &is_null)))
		{
			/* parse values for the entry (lines 10-12 in T1) */

			if (f == field_count)
			{
				zabbix_log(LOG_LEVEL_WARNING, "invalid number of fields \"%.*s\"",
						jp_row.end - jp_row.start + 1, jp_row.start);
				goto db_error;
			}

			if (0 == insert)
				zbx_snprintf_alloc(&sql, &sql_alloc, &sql_offset, "%s=", fields[f]->name);

			if (0 != is_null)
			{
				if (0 != (fields[f]->flags & ZBX_NOTNULL))
				{
					zabbix_log(LOG_LEVEL_WARNING, "column '%s' cannot be null", fields[f]->name);
					goto db_error;
				}

				zbx_strcpy_alloc(&sql, &sql_alloc, &sql_offset, "null,");
			}
			else
			{
				switch (fields[f]->type)
				{
					case ZBX_TYPE_INT:
					case ZBX_TYPE_UINT:
					case ZBX_TYPE_ID:
					case ZBX_TYPE_FLOAT:
						zbx_snprintf_alloc(&sql, &sql_alloc, &sql_offset, "%s,", buf);
						break;
					default:
						esc = DBdyn_escape_string(buf);
						zbx_snprintf_alloc(&sql, &sql_alloc, &sql_offset, "'%s',", esc);
						zbx_free(esc);
				}
			}

			f++;
		}

		if (f != field_count)
		{
			zabbix_log(LOG_LEVEL_WARNING, "invalid number of fields \"%.*s\"",
					jp_row.end - jp_row.start + 1, jp_row.start);
			goto db_error;
		}

		sql_offset--;
		if (0 != insert)
		{
			zbx_strcpy_alloc(&sql, &sql_alloc, &sql_offset, ");\n");
		}
		else
		{
			zbx_snprintf_alloc(&sql, &sql_alloc, &sql_offset, " where %s=" ZBX_FS_UI64 ";\n",
					table->recid, recid);
		}

		if (SUCCEED != DBexecute_overflowed_sql(&sql, &sql_alloc, &sql_offset))
			goto db_error;
	}

	DBend_multiple_update(&sql, &sql_alloc, &sql_offset);

	if (sql_offset > 16)	/* In ORACLE always present begin..end; */
		if (ZBX_DB_OK > DBexecute("%s", sql))
			goto db_error;

	uint64_array_remove(*del, del_num, new, new_num);

	del_alloc = *del_num;
	*del = zbx_realloc(*del, del_alloc * sizeof(zbx_uint64_t));

	ret = SUCCEED;
json_error:
	if (SUCCEED != ret)
		zabbix_log(LOG_LEVEL_DEBUG, "cannot process table \"%s\": %s", tablename, zbx_json_strerror());
db_error:
	zbx_free(sql);
	zbx_free(new);
exit:
	zabbix_log(LOG_LEVEL_DEBUG, "End of %s():%s", __function_name, zbx_result_string(ret));

	return ret;
}

/******************************************************************************
 *                                                                            *
 * Function: process_proxyconfig                                              *
 *                                                                            *
 * Purpose: update configuration                                              *
 *                                                                            *
 * Author: Alexander Vladishev                                                *
 *                                                                            *
 ******************************************************************************/
void	process_proxyconfig(struct zbx_json_parse *jp_data)
{
	struct table_ids
	{
		char		*table_name;
		zbx_uint64_t	*ids;
		int		id_num;
	};

	const char		*__function_name = "process_proxyconfig";
	char			buf[MAX_STRING_LEN];
	size_t			len = sizeof(buf);
	const char		*p = NULL;
	struct zbx_json_parse	jp_obj;
	int			i, ret = SUCCEED;

	struct table_ids	*delete_ids;
	zbx_vector_ptr_t	table_order;
	const ZBX_TABLE		*table = NULL;
	char 			*sql = NULL;
	size_t 			sql_alloc = 512, sql_offset = 0;

	zabbix_log(LOG_LEVEL_DEBUG, "In %s()", __function_name);

	zbx_vector_ptr_create(&table_order);

	DBbegin();

	/* iterate the tables (lines 2, 22 and 25 in T1) */
	while (NULL != (p = zbx_json_pair_next(jp_data, p, buf, len)) && SUCCEED == ret)
	{
		if (FAIL == zbx_json_brackets_open(p, &jp_obj))
		{
			zabbix_log(LOG_LEVEL_DEBUG, "cannot process table \"%s\": %s", buf, zbx_json_strerror());
			ret = FAIL;
			break;
		}

		delete_ids = zbx_malloc(NULL, sizeof(struct table_ids));
		delete_ids->table_name = zbx_strdup(NULL, buf);
		delete_ids->ids = NULL;
		delete_ids->id_num = 0;
		zbx_vector_ptr_append(&table_order, delete_ids);

		ret = process_proxyconfig_table(jp_data, buf, &jp_obj, &delete_ids->ids, &delete_ids->id_num);
	}

	sql = zbx_malloc(sql, sql_alloc * sizeof(char));

	DBbegin_multiple_update(&sql, &sql_alloc, &sql_offset);

	for (i = table_order.values_num - 1; 0 <= i; i--)
	{
		delete_ids = table_order.values[i];

		if (SUCCEED == ret && 0 < delete_ids->id_num)
		{
			if (NULL == (table = DBget_table(delete_ids->table_name)))
			{
				zabbix_log(LOG_LEVEL_WARNING, "invalid table name \"%s\"", delete_ids->table_name);
				break;
			}

			zbx_snprintf_alloc(&sql, &sql_alloc, &sql_offset, "delete from %s where", table->table);
			DBadd_condition_alloc(&sql, &sql_alloc, &sql_offset, table->recid,
					delete_ids->ids, delete_ids->id_num);
			zbx_strcpy_alloc(&sql, &sql_alloc, &sql_offset, ";\n");

			ret = DBexecute_overflowed_sql(&sql, &sql_alloc, &sql_offset);
		}

		zbx_free(delete_ids->ids);
		zbx_free(delete_ids->table_name);
		zbx_free(delete_ids);
	}

	if (SUCCEED == ret)
	{
		DBend_multiple_update(&sql, &sql_alloc, &sql_offset);

		if (sql_offset > 16)	/* In ORACLE always present begin..end; */
			if (ZBX_DB_OK > DBexecute("%s", sql))
				ret = FAIL;
	}

	zbx_free(sql);
	zbx_vector_ptr_destroy(&table_order);

	if (SUCCEED == ret)
	{
		DBcommit();
		DCsync_configuration();
	}
	else
	{
		zabbix_log(LOG_LEVEL_ERR, "failed to update local proxy cofiguration copy");
		DBrollback();
	}

	zabbix_log(LOG_LEVEL_DEBUG, "End of %s()", __function_name);
}

/******************************************************************************
 *                                                                            *
 * Function: get_host_availability_data                                       *
 *                                                                            *
 * Return value:  SUCCEED - processed successfully                            *
 *                FAIL - an error occurred                                    *
 *                                                                            *
 * Author: Alexander Vladishev                                                *
 *                                                                            *
 ******************************************************************************/
int	get_host_availability_data(struct zbx_json *j)
{
	typedef struct
	{
		zbx_uint64_t	hostid;
		char		*error, *snmp_error, *ipmi_error, *jmx_error;
		unsigned char	available, snmp_available, ipmi_available, jmx_available;
	}
	zbx_host_availability_t;

	const char			*__function_name = "get_host_availability_data";
	zbx_uint64_t			hostid;
	size_t				sz;
	DB_RESULT			result;
	DB_ROW				row;
	static zbx_host_availability_t	*ha = NULL;
	static int			ha_alloc = 0, ha_num = 0;
	int				index, new, ret = FAIL;
	unsigned char			available, snmp_available, ipmi_available, jmx_available;
	char				*error, *snmp_error, *ipmi_error, *jmx_error;

	zabbix_log(LOG_LEVEL_DEBUG, "In %s()", __function_name);

	zbx_json_addarray(j, ZBX_PROTO_TAG_DATA);

	result = DBselect(
			"select hostid,available,error,snmp_available,snmp_error,"
				"ipmi_available,ipmi_error,jmx_available,jmx_error"
			" from hosts");

	while (NULL != (row = DBfetch(result)))
	{
		ZBX_STR2UINT64(hostid, row[0]);

		new = 0;

		index = get_nearestindex(ha, sizeof(zbx_host_availability_t), ha_num, hostid);

		if (index == ha_num || ha[index].hostid != hostid)
		{
			if (ha_num == ha_alloc)
			{
				ha_alloc += 8;
				ha = zbx_realloc(ha, sizeof(zbx_host_availability_t) * ha_alloc);
			}

			if (0 != (sz = sizeof(zbx_host_availability_t) * (ha_num - index)))
				memmove(&ha[index + 1], &ha[index], sz);
			ha_num++;

			ha[index].hostid = hostid;
			ha[index].available = HOST_AVAILABLE_UNKNOWN;
			ha[index].snmp_available = HOST_AVAILABLE_UNKNOWN;
			ha[index].ipmi_available = HOST_AVAILABLE_UNKNOWN;
			ha[index].jmx_available = HOST_AVAILABLE_UNKNOWN;
			ha[index].error = NULL;
			ha[index].snmp_error = NULL;
			ha[index].ipmi_error = NULL;
			ha[index].jmx_error = NULL;

			new = 1;
		}

		available = (unsigned char)atoi(row[1]);
		error = row[2];
		snmp_available = (unsigned char)atoi(row[3]);
		snmp_error = row[4];
		ipmi_available = (unsigned char)atoi(row[5]);
		ipmi_error = row[6];
		jmx_available = (unsigned char)atoi(row[7]);
		jmx_error = row[8];

		if (0 == new && ha[index].available == available &&
				ha[index].snmp_available == snmp_available &&
				ha[index].ipmi_available == ipmi_available &&
				ha[index].jmx_available == jmx_available &&
				0 == strcmp(ha[index].error, error) &&
				0 == strcmp(ha[index].snmp_error, snmp_error) &&
				0 == strcmp(ha[index].ipmi_error, ipmi_error) &&
				0 == strcmp(ha[index].jmx_error, jmx_error))
			continue;

		zbx_json_addobject(j, NULL);

		zbx_json_adduint64(j, ZBX_PROTO_TAG_HOSTID, hostid);

		if (1 == new || ha[index].available != available)
		{
			zbx_json_adduint64(j, ZBX_PROTO_TAG_AVAILABLE, available);
			ha[index].available = available;
		}

		if (1 == new || ha[index].snmp_available != snmp_available)
		{
			zbx_json_adduint64(j, ZBX_PROTO_TAG_SNMP_AVAILABLE, snmp_available);
			ha[index].snmp_available = snmp_available;
		}

		if (1 == new || ha[index].ipmi_available != ipmi_available)
		{
			zbx_json_adduint64(j, ZBX_PROTO_TAG_IPMI_AVAILABLE, ipmi_available);
			ha[index].ipmi_available = ipmi_available;
		}

		if (1 == new || ha[index].jmx_available != jmx_available)
		{
			zbx_json_adduint64(j, ZBX_PROTO_TAG_JMX_AVAILABLE, jmx_available);
			ha[index].jmx_available = jmx_available;
		}

		if (1 == new || 0 != strcmp(ha[index].error, error))
		{
			zbx_json_addstring(j, ZBX_PROTO_TAG_ERROR, error, ZBX_JSON_TYPE_STRING);
			ZBX_STRDUP(ha[index].error, error);
		}

		if (1 == new || 0 != strcmp(ha[index].snmp_error, snmp_error))
		{
			zbx_json_addstring(j, ZBX_PROTO_TAG_SNMP_ERROR, snmp_error, ZBX_JSON_TYPE_STRING);
			ZBX_STRDUP(ha[index].snmp_error, snmp_error);
		}

		if (1 == new || 0 != strcmp(ha[index].ipmi_error, ipmi_error))
		{
			zbx_json_addstring(j, ZBX_PROTO_TAG_IPMI_ERROR, ipmi_error, ZBX_JSON_TYPE_STRING);
			ZBX_STRDUP(ha[index].ipmi_error, ipmi_error);
		}

		if (1 == new || 0 != strcmp(ha[index].jmx_error, jmx_error))
		{
			zbx_json_addstring(j, ZBX_PROTO_TAG_JMX_ERROR, jmx_error, ZBX_JSON_TYPE_STRING);
			ZBX_STRDUP(ha[index].jmx_error, jmx_error);
		}

		zbx_json_close(j);

		ret = SUCCEED;
	}
	DBfree_result(result);

	zbx_json_close(j);

	zabbix_log(LOG_LEVEL_DEBUG, "End of %s():%s", __function_name, zbx_result_string(ret));

	return ret;
}

/******************************************************************************
 *                                                                            *
 * Function: process_host_availability                                        *
 *                                                                            *
 * Purpose: update proxy hosts availability                                   *
 *                                                                            *
 * Author: Alexander Vladishev                                                *
 *                                                                            *
 ******************************************************************************/
void	process_host_availability(struct zbx_json_parse *jp)
{
	const char		*__function_name = "process_host_availability";
	zbx_uint64_t		hostid;
	struct zbx_json_parse	jp_data, jp_row;
	const char		*p = NULL;
	char			tmp[HOST_ERROR_LEN_MAX], *sql = NULL, *error_esc;
	size_t			sql_alloc = 4 * ZBX_KIBIBYTE, sql_offset = 0, tmp_offset;
	int			no_data;

	zabbix_log(LOG_LEVEL_DEBUG, "In %s()", __function_name);

	/* "data" tag lists the hosts */
	if (SUCCEED != zbx_json_brackets_by_name(jp, ZBX_PROTO_TAG_DATA, &jp_data))
	{
		zabbix_log(LOG_LEVEL_WARNING, "invalid host availability data: %s", zbx_json_strerror());
		goto exit;
	}

	if (SUCCEED == zbx_json_object_is_empty(&jp_data))
		goto exit;

	sql = zbx_malloc(sql, sql_alloc);

	DBbegin();

	DBbegin_multiple_update(&sql, &sql_alloc, &sql_offset);

	while (NULL != (p = zbx_json_next(&jp_data, p)))	/* iterate the host entries */
	{
		if (SUCCEED != zbx_json_brackets_open(p, &jp_row))
		{
			zabbix_log(LOG_LEVEL_WARNING, "invalid host availability data: %s", zbx_json_strerror());
			continue;
		}

		tmp_offset = sql_offset;
		no_data = 1;

		zbx_strcpy_alloc(&sql, &sql_alloc, &sql_offset, "update hosts set ");

		if (SUCCEED == zbx_json_value_by_name(&jp_row, ZBX_PROTO_TAG_AVAILABLE, tmp, sizeof(tmp)))
		{
			zbx_snprintf_alloc(&sql, &sql_alloc, &sql_offset, "available=%d,", atoi(tmp));
			no_data = 0;
		}

		if (SUCCEED == zbx_json_value_by_name(&jp_row, ZBX_PROTO_TAG_SNMP_AVAILABLE, tmp, sizeof(tmp)))
		{
			zbx_snprintf_alloc(&sql, &sql_alloc, &sql_offset, "snmp_available=%d,", atoi(tmp));
			no_data = 0;
		}

		if (SUCCEED == zbx_json_value_by_name(&jp_row, ZBX_PROTO_TAG_IPMI_AVAILABLE, tmp, sizeof(tmp)))
		{
			zbx_snprintf_alloc(&sql, &sql_alloc, &sql_offset, "ipmi_available=%d,", atoi(tmp));
			no_data = 0;
		}

		if (SUCCEED == zbx_json_value_by_name(&jp_row, ZBX_PROTO_TAG_JMX_AVAILABLE, tmp, sizeof(tmp)))
		{
			zbx_snprintf_alloc(&sql, &sql_alloc, &sql_offset, "jmx_available=%d,", atoi(tmp));
			no_data = 0;
		}

		if (SUCCEED == zbx_json_value_by_name(&jp_row, ZBX_PROTO_TAG_ERROR, tmp, sizeof(tmp)))
		{
			error_esc = DBdyn_escape_string_len(tmp, HOST_ERROR_LEN);
			zbx_snprintf_alloc(&sql, &sql_alloc, &sql_offset, "error='%s',", error_esc);
			zbx_free(error_esc);
			no_data = 0;
		}

		if (SUCCEED == zbx_json_value_by_name(&jp_row, ZBX_PROTO_TAG_SNMP_ERROR, tmp, sizeof(tmp)))
		{
			error_esc = DBdyn_escape_string_len(tmp, HOST_ERROR_LEN);
			zbx_snprintf_alloc(&sql, &sql_alloc, &sql_offset, "snmp_error='%s',", error_esc);
			zbx_free(error_esc);
			no_data = 0;
		}

		if (SUCCEED == zbx_json_value_by_name(&jp_row, ZBX_PROTO_TAG_IPMI_ERROR, tmp, sizeof(tmp)))
		{
			error_esc = DBdyn_escape_string_len(tmp, HOST_ERROR_LEN);
			zbx_snprintf_alloc(&sql, &sql_alloc, &sql_offset, "ipmi_error='%s',", error_esc);
			zbx_free(error_esc);
			no_data = 0;
		}

		if (SUCCEED == zbx_json_value_by_name(&jp_row, ZBX_PROTO_TAG_JMX_ERROR, tmp, sizeof(tmp)))
		{
			error_esc = DBdyn_escape_string_len(tmp, HOST_ERROR_LEN);
			zbx_snprintf_alloc(&sql, &sql_alloc, &sql_offset, "jmx_error='%s',", error_esc);
			zbx_free(error_esc);
			no_data = 0;
		}

		if (SUCCEED != zbx_json_value_by_name(&jp_row, ZBX_PROTO_TAG_HOSTID, tmp, sizeof(tmp)))
		{
			zabbix_log(LOG_LEVEL_WARNING, "invalid host availability data: %s", zbx_json_strerror());
			sql_offset = tmp_offset;
			continue;
		}

		if (SUCCEED != is_uint64(tmp, &hostid) || 1 == no_data)
		{
			zabbix_log(LOG_LEVEL_WARNING, "invalid host availability data");
			sql_offset = tmp_offset;
			continue;
		}

		sql_offset--;
		zbx_snprintf_alloc(&sql, &sql_alloc, &sql_offset, " where hostid=" ZBX_FS_UI64 ";\n", hostid);

		DBexecute_overflowed_sql(&sql, &sql_alloc, &sql_offset);
	}

	DBend_multiple_update(&sql, &sql_alloc, &sql_offset);

	if (sql_offset > 16)	/* In ORACLE always present begin..end; */
		DBexecute("%s", sql);

	DBcommit();

	zbx_free(sql);
exit:
	zabbix_log(LOG_LEVEL_DEBUG, "End of %s()", __function_name);
}

/******************************************************************************
 *                                                                            *
 * Function: proxy_get_lastid                                                 *
 *                                                                            *
 * Author: Alexander Vladishev                                                *
 *                                                                            *
 ******************************************************************************/
static void	proxy_get_lastid(const ZBX_HISTORY_TABLE *ht, zbx_uint64_t *lastid)
{
	const char	*__function_name = "proxy_get_lastid";
	DB_RESULT	result;
	DB_ROW		row;

	zabbix_log(LOG_LEVEL_DEBUG, "In %s() [%s.%s]", __function_name, ht->table, ht->lastfieldname);

	result = DBselect("select nextid from ids where table_name='%s' and field_name='%s'",
			ht->table,
			ht->lastfieldname);

	if (NULL == (row = DBfetch(result)))
		*lastid = 0;
	else
		ZBX_STR2UINT64(*lastid, row[0]);

	DBfree_result(result);

	zabbix_log(LOG_LEVEL_DEBUG, "End of %s():" ZBX_FS_UI64,	__function_name, *lastid);
}

/******************************************************************************
 *                                                                            *
 * Function: proxy_set_lastid                                                 *
 *                                                                            *
 * Author: Alexander Vladishev                                                *
 *                                                                            *
 ******************************************************************************/
static void	proxy_set_lastid(const ZBX_HISTORY_TABLE *ht, const zbx_uint64_t lastid)
{
	const char	*__function_name = "proxy_set_lastid";
	DB_RESULT	result;
	DB_ROW		row;

	zabbix_log(LOG_LEVEL_DEBUG, "In %s() [%s.%s:" ZBX_FS_UI64 "]",
			__function_name, ht->table, ht->lastfieldname, lastid);

	result = DBselect("select 1 from ids where table_name='%s' and field_name='%s'",
			ht->table,
			ht->lastfieldname);

	if (NULL == (row = DBfetch(result)))
	{
		DBexecute("insert into ids (nodeid,table_name,field_name,nextid)"
				"values (0,'%s','%s'," ZBX_FS_UI64 ")",
				ht->table,
				ht->lastfieldname,
				lastid);
	}
	else
	{
		DBexecute("update ids set nextid=" ZBX_FS_UI64
				" where table_name='%s' and field_name='%s'",
				lastid,
				ht->table,
				ht->lastfieldname);
	}

	DBfree_result(result);

	zabbix_log(LOG_LEVEL_DEBUG, "End of %s()", __function_name);
}

void	proxy_set_hist_lastid(const zbx_uint64_t lastid)
{
	proxy_set_lastid(&ht, lastid);
}

void	proxy_set_dhis_lastid(const zbx_uint64_t lastid)
{
	proxy_set_lastid(&dht, lastid);
}

void	proxy_set_areg_lastid(const zbx_uint64_t lastid)
{
	proxy_set_lastid(&areg, lastid);
}

/******************************************************************************
 *                                                                            *
 * Function: proxy_get_history_data                                           *
 *                                                                            *
 * Author: Alexander Vladishev                                                *
 *                                                                            *
 ******************************************************************************/
static int	proxy_get_history_data(struct zbx_json *j, const ZBX_HISTORY_TABLE *ht, zbx_uint64_t *lastid)
{
	const char	*__function_name = "proxy_get_history_data";
	size_t		offset = 0;
	int		f, records = 0;
	char		sql[MAX_STRING_LEN];
	DB_RESULT	result;
	DB_ROW		row;
	zbx_uint64_t	id;

	zabbix_log(LOG_LEVEL_DEBUG, "In %s() table:'%s'", __function_name, ht->table);

	*lastid = 0;

	proxy_get_lastid(ht, &id);

	offset += zbx_snprintf(sql + offset, sizeof(sql) - offset, "select p.id");

	for (f = 0; NULL != ht->fields[f].field; f++)
		offset += zbx_snprintf(sql + offset, sizeof(sql) - offset, ",%s", ht->fields[f].field);

	offset += zbx_snprintf(sql + offset, sizeof(sql) - offset, " from %s%s p"
			" where %sp.id>" ZBX_FS_UI64 " order by p.id",
			ht->from, ht->table,
			ht->where,
			id);

	result = DBselectN(sql, ZBX_MAX_HRECORDS);

	while (NULL != (row = DBfetch(result)))
	{
		zbx_json_addobject(j, NULL);

		ZBX_STR2UINT64(*lastid, row[0]);

		for (f = 0; NULL != ht->fields[f].field; f++)
		{
			if (NULL != ht->fields[f].default_value && 0 == strcmp(row[f + 1], ht->fields[f].default_value))
				continue;

			zbx_json_addstring(j, ht->fields[f].tag, row[f + 1], ht->fields[f].jt);
		}

		records++;

		zbx_json_close(j);
	}

	DBfree_result(result);

	zabbix_log(LOG_LEVEL_DEBUG, "End of %s():%d lastid:" ZBX_FS_UI64, __function_name, records, *lastid);

	return records;
}

int	proxy_get_hist_data(struct zbx_json *j, zbx_uint64_t *lastid)
{
	return proxy_get_history_data(j, &ht, lastid);
}

int	proxy_get_dhis_data(struct zbx_json *j, zbx_uint64_t *lastid)
{
	return proxy_get_history_data(j, &dht, lastid);
}

int	proxy_get_areg_data(struct zbx_json *j, zbx_uint64_t *lastid)
{
	return proxy_get_history_data(j, &areg, lastid);
}

void	calc_timestamp(char *line, int *timestamp, char *format)
{

	const char	*__function_name = "calc_timestamp";
	int		hh, mm, ss, yyyy, dd, MM;
	int		hhc = 0, mmc = 0, ssc = 0, yyyyc = 0, ddc = 0, MMc = 0;
	int		i, num;
	struct tm	tm;
	time_t		t;

	zabbix_log(LOG_LEVEL_DEBUG, "In %s()", __function_name);

	hh = mm = ss = yyyy = dd = MM = 0;

	for (i = 0; '\0' != format[i] && '\0' != line[i]; i++)
	{
		if (0 == isdigit(line[i]))
			continue;

		num = (int)line[i] - 48;

		switch ((char)format[i])
		{
			case 'h':
				hh = 10 * hh + num;
				hhc++;
				break;
			case 'm':
				mm = 10 * mm + num;
				mmc++;
				break;
			case 's':
				ss = 10 * ss + num;
				ssc++;
				break;
			case 'y':
				yyyy = 10 * yyyy + num;
				yyyyc++;
				break;
			case 'd':
				dd = 10 * dd + num;
				ddc++;
				break;
			case 'M':
				MM = 10 * MM + num;
				MMc++;
				break;
		}
	}

	zabbix_log(LOG_LEVEL_DEBUG, "%s() %02d:%02d:%02d %02d/%02d/%04d",
			__function_name, hh, mm, ss, MM, dd, yyyy);

	/* seconds can be ignored, no ssc here */
	if (0 != hhc && 0 != mmc && 0 != yyyyc && 0 != ddc && 0 != MMc)
	{
		tm.tm_sec = ss;
		tm.tm_min = mm;
		tm.tm_hour = hh;
		tm.tm_mday = dd;
		tm.tm_mon = MM - 1;
		tm.tm_year = yyyy - 1900;
		tm.tm_isdst = -1;

		if (0 < (t = mktime(&tm)))
			*timestamp = t;
	}

	zabbix_log(LOG_LEVEL_DEBUG, "End of %s() timestamp:%d",	__function_name, *timestamp);
}

/******************************************************************************
 *                                                                            *
 * Function: process_mass_data                                                *
 *                                                                            *
 * Purpose: process new item value                                            *
 *                                                                            *
 * Parameters: sock         - [IN] descriptor of agent-server socket          *
 *                                 connection. NULL for proxy connection      *
 *             proxy_hostid - [IN] proxy identificator from database          *
 *             values       - [IN] array of incoming values                   *
 *             value_num    - [IN] number of elements in array                *
 *             processed    - [OUT] number of processed elements              *
 *                                                                            *
 * Author: Alexander Vladishev                                                *
 *                                                                            *
 ******************************************************************************/
void	process_mass_data(zbx_sock_t *sock, zbx_uint64_t proxy_hostid,
		AGENT_VALUE *values, int value_num, int *processed)
{
	const char	*__function_name = "process_mass_data";
	AGENT_RESULT	agent;
	DC_ITEM		item;
	int		i;

	zabbix_log(LOG_LEVEL_DEBUG, "In %s()", __function_name);

	for (i = 0; i < value_num; i++)
	{
		if (SUCCEED != DCconfig_get_item_by_key(&item, proxy_hostid, values[i].host_name, values[i].key))
			continue;

		if (HOST_MAINTENANCE_STATUS_ON == item.host.maintenance_status &&
				MAINTENANCE_TYPE_NODATA == item.host.maintenance_type &&
				item.host.maintenance_from <= values[i].ts.sec)
			continue;

		if (ITEM_TYPE_INTERNAL == item.type || ITEM_TYPE_AGGREGATE == item.type || ITEM_TYPE_CALCULATED == item.type)
			continue;

		if (0 == proxy_hostid && ITEM_TYPE_TRAPPER != item.type && ITEM_TYPE_ZABBIX_ACTIVE != item.type)
			continue;

		if (ITEM_TYPE_TRAPPER == item.type && 0 == proxy_hostid &&
				FAIL == zbx_tcp_check_security(sock, item.trapper_hosts, 1))
		{
			zabbix_log(LOG_LEVEL_WARNING, "process data failed: %s", zbx_tcp_strerror());
			continue;
		}

		if (0 == strcmp(values[i].value, "ZBX_NOTSUPPORTED"))
		{
			dc_add_history(item.itemid, item.value_type, item.flags, NULL, &values[i].ts,
					ITEM_STATUS_NOTSUPPORTED, values[i].value, 0, NULL, 0, 0, 0, 0);

			if (NULL != processed)
				(*processed)++;
		}
		else
		{
			init_result(&agent);

			if (SUCCEED == set_result_type(&agent, item.value_type,
					proxy_hostid ? ITEM_DATA_TYPE_DECIMAL : item.data_type, values[i].value))
			{
				if (ITEM_VALUE_TYPE_LOG == item.value_type)
					calc_timestamp(values[i].value, &values[i].timestamp, item.logtimefmt);

				if (NULL != values[i].source)
					zbx_replace_invalid_utf8(values[i].source);

				dc_add_history(item.itemid, item.value_type, item.flags, &agent, &values[i].ts,
						ITEM_STATUS_ACTIVE, NULL, values[i].timestamp, values[i].source,
						values[i].severity, values[i].logeventid, values[i].lastlogsize,
						values[i].mtime);

				if (NULL != processed)
					(*processed)++;
			}
			else if (ISSET_MSG(&agent))
			{
				zabbix_log(LOG_LEVEL_DEBUG, "item [%s:%s] error: %s",
						item.host.host, item.key_orig, agent.msg);

				dc_add_history(item.itemid, item.value_type, item.flags, NULL, &values[i].ts,
						ITEM_STATUS_NOTSUPPORTED, agent.msg, 0, NULL, 0, 0, 0, 0);
			}
			else
				THIS_SHOULD_NEVER_HAPPEN; /* set_result_type() always sets MSG result if not SUCCEED */

			free_result(&agent);
		}
	}

	zabbix_log(LOG_LEVEL_DEBUG, "End of %s()", __function_name);
}

static void	clean_agent_values(AGENT_VALUE *values, int value_num)
{
	int	i;

	for (i = 0; i < value_num; i++)
	{
		zbx_free(values[i].value);
		zbx_free(values[i].source);
	}
}

/******************************************************************************
 *                                                                            *
 * Function: process_hist_data                                                *
 *                                                                            *
 * Purpose: process values sent by proxies, active agents and senders         *
 *                                                                            *
 * Return value:  SUCCEED - processed successfully                            *
 *                FAIL - an error occurred                                    *
 *                                                                            *
 * Author: Alexander Vladishev, Alexei Vladishev                              *
 *                                                                            *
 ******************************************************************************/
int	process_hist_data(zbx_sock_t *sock, struct zbx_json_parse *jp,
		const zbx_uint64_t proxy_hostid, char *info, int max_info_size)
{
#define VALUES_MAX	256
	const char		*__function_name = "process_hist_data";
	struct zbx_json_parse	jp_data, jp_row;
	const char		*p;
	char			tmp[MAX_BUFFER_LEN];
	int			ret = FAIL, processed = 0, value_num = 0, total_num = 0;
	double			sec;
	zbx_timespec_t		ts, proxy_timediff;
	static AGENT_VALUE	*values = NULL, *av;

	zabbix_log(LOG_LEVEL_DEBUG, "In %s()", __function_name);

	sec = zbx_time();

	zbx_timespec(&ts);
	proxy_timediff.sec = 0;
	proxy_timediff.ns = 0;

	if (NULL == values)
		values = zbx_malloc(values, VALUES_MAX * sizeof(AGENT_VALUE));

	if (SUCCEED == zbx_json_value_by_name(jp, ZBX_PROTO_TAG_CLOCK, tmp, sizeof(tmp)))
	{
		proxy_timediff.sec = ts.sec - atoi(tmp);

		if (SUCCEED == zbx_json_value_by_name(jp, ZBX_PROTO_TAG_NS, tmp, sizeof(tmp)))
		{
			proxy_timediff.ns = ts.ns - atoi(tmp);

			if (proxy_timediff.ns < 0)
			{
				proxy_timediff.sec--;
				proxy_timediff.ns += 1000000000;
			}
		}
	}

	/* "data" tag lists the item keys */
	if (NULL == (p = zbx_json_pair_by_name(jp, ZBX_PROTO_TAG_DATA)))
		zabbix_log(LOG_LEVEL_WARNING, "cannot find \"data\" pair");
	else if (FAIL == zbx_json_brackets_open(p, &jp_data))
		zabbix_log(LOG_LEVEL_WARNING, "cannot process json request: %s", zbx_json_strerror());
	else
		ret = SUCCEED;

	p = NULL;
	while (SUCCEED == ret && NULL != (p = zbx_json_next(&jp_data, p)))	/* iterate the item key entries */
	{
		if (FAIL == (ret = zbx_json_brackets_open(p, &jp_row)))
			break;

		av = &values[value_num];

		memset(av, 0, sizeof(AGENT_VALUE));

		if (SUCCEED == zbx_json_value_by_name(&jp_row, ZBX_PROTO_TAG_CLOCK, tmp, sizeof(tmp)))
		{
			av->ts.sec = atoi(tmp) + proxy_timediff.sec;

			if (SUCCEED == zbx_json_value_by_name(&jp_row, ZBX_PROTO_TAG_NS, tmp, sizeof(tmp)))
			{
				av->ts.ns = atoi(tmp) + proxy_timediff.ns;

				if (av->ts.ns > 999999999)
				{
					av->ts.sec++;
					av->ts.ns -= 1000000000;
				}
			}
			else
				av->ts.ns = -1;
		}
		else
			zbx_timespec(&av->ts);

		if (FAIL == zbx_json_value_by_name(&jp_row, ZBX_PROTO_TAG_HOST, av->host_name, sizeof(av->host_name)))
			continue;

		if (FAIL == zbx_json_value_by_name(&jp_row, ZBX_PROTO_TAG_KEY, av->key, sizeof(av->key)))
			continue;

		if (FAIL == zbx_json_value_by_name(&jp_row, ZBX_PROTO_TAG_VALUE, tmp, sizeof(tmp)))
			continue;

		av->value = strdup(tmp);

		if (SUCCEED == zbx_json_value_by_name(&jp_row, ZBX_PROTO_TAG_LOGLASTSIZE, tmp, sizeof(tmp)))
			av->lastlogsize = atoi(tmp);

		if (SUCCEED == zbx_json_value_by_name(&jp_row, ZBX_PROTO_TAG_MTIME, tmp, sizeof(tmp)))
			av->mtime = atoi(tmp);

		if (SUCCEED == zbx_json_value_by_name(&jp_row, ZBX_PROTO_TAG_LOGTIMESTAMP, tmp, sizeof(tmp)))
			av->timestamp = atoi(tmp);

		if (SUCCEED == zbx_json_value_by_name(&jp_row, ZBX_PROTO_TAG_LOGSOURCE, tmp, sizeof(tmp)))
			av->source = strdup(tmp);

		if (SUCCEED == zbx_json_value_by_name(&jp_row, ZBX_PROTO_TAG_LOGSEVERITY, tmp, sizeof(tmp)))
			av->severity = atoi(tmp);

		if (SUCCEED == zbx_json_value_by_name(&jp_row, ZBX_PROTO_TAG_LOGEVENTID, tmp, sizeof(tmp)))
			av->logeventid = atoi(tmp);

		value_num++;

		if (VALUES_MAX == value_num)
		{
			process_mass_data(sock, proxy_hostid, values, value_num, &processed);

			clean_agent_values(values, value_num);
			total_num += value_num;
			value_num = 0;
		}
	}

	if (0 < value_num)
		process_mass_data(sock, proxy_hostid, values, value_num, &processed);

	clean_agent_values(values, value_num);
	total_num += value_num;

	if (NULL != info)
	{
		zbx_snprintf(info, max_info_size, "Processed %d Failed %d Total %d Seconds spent " ZBX_FS_DBL,
				processed, total_num - processed, total_num, zbx_time() - sec);
	}

	zabbix_log(LOG_LEVEL_DEBUG, "End of %s():%s", __function_name, zbx_result_string(ret));

	return ret;
}

/******************************************************************************
 *                                                                            *
 * Function: process_dhis_data                                                *
 *                                                                            *
 * Purpose: update discovery data, received from proxy                        *
 *                                                                            *
 * Author: Alexander Vladishev                                                *
 *                                                                            *
 ******************************************************************************/
void	process_dhis_data(struct zbx_json_parse *jp)
{
	const char		*__function_name = "process_dhis_data";
	DB_RESULT		result;
	DB_ROW			row;
	DB_DRULE		drule;
	DB_DCHECK		dcheck;
	DB_DHOST		dhost;
	zbx_uint64_t		last_druleid = 0;
	struct zbx_json_parse	jp_data, jp_row;
	int			port, status, ret;
	const char		*p = NULL;
	char			last_ip[INTERFACE_IP_LEN_MAX], ip[INTERFACE_IP_LEN_MAX], key_[ITEM_KEY_LEN_MAX],
				tmp[MAX_STRING_LEN], value[DSERVICE_VALUE_LEN_MAX], dns[INTERFACE_DNS_LEN_MAX];
	time_t			now, hosttime, itemtime;

	zabbix_log(LOG_LEVEL_DEBUG, "In %s()", __function_name);

	now = time(NULL);

	if (SUCCEED != (ret = zbx_json_value_by_name(jp, ZBX_PROTO_TAG_CLOCK, tmp, sizeof(tmp))))
		goto exit;

	if (SUCCEED != (ret = zbx_json_brackets_by_name(jp, ZBX_PROTO_TAG_DATA, &jp_data)))
		goto exit;

	hosttime = atoi(tmp);

	memset(&drule, 0, sizeof(drule));
	*last_ip = '\0';

	while (NULL != (p = zbx_json_next(&jp_data, p)))
	{
		if (FAIL == (ret = zbx_json_brackets_open(p, &jp_row)))
			break;

		memset(&dcheck, 0, sizeof(dcheck));
		*key_ = '\0';
		*value = '\0';
		*dns = '\0';
		port = 0;
		status = 0;
		dcheck.key_ = key_;

		if (FAIL == zbx_json_value_by_name(&jp_row, ZBX_PROTO_TAG_CLOCK, tmp, sizeof(tmp)))
			goto json_parse_error;
		itemtime = now - (hosttime - atoi(tmp));

		if (FAIL == zbx_json_value_by_name(&jp_row, ZBX_PROTO_TAG_DRULE, tmp, sizeof(tmp)))
			goto json_parse_error;
		ZBX_STR2UINT64(drule.druleid, tmp);

		if (FAIL == zbx_json_value_by_name(&jp_row, ZBX_PROTO_TAG_TYPE, tmp, sizeof(tmp)))
			goto json_parse_error;
		dcheck.type = atoi(tmp);

		if (FAIL == zbx_json_value_by_name(&jp_row, ZBX_PROTO_TAG_DCHECK, tmp, sizeof(tmp)))
			goto json_parse_error;
		if ('\0' != *tmp)
			ZBX_STR2UINT64(dcheck.dcheckid, tmp);
		else if (-1 != dcheck.type)
			goto json_parse_error;

		if (FAIL == zbx_json_value_by_name(&jp_row, ZBX_PROTO_TAG_IP, ip, sizeof(ip)))
			goto json_parse_error;

		if (SUCCEED == zbx_json_value_by_name(&jp_row, ZBX_PROTO_TAG_PORT, tmp, sizeof(tmp)))
			port = atoi(tmp);

		zbx_json_value_by_name(&jp_row, ZBX_PROTO_TAG_KEY, key_, sizeof(key_));
		zbx_json_value_by_name(&jp_row, ZBX_PROTO_TAG_VALUE, value, sizeof(value));
		zbx_json_value_by_name(&jp_row, ZBX_PROTO_TAG_DNS, dns, sizeof(dns));

		if (SUCCEED == zbx_json_value_by_name(&jp_row, ZBX_PROTO_TAG_STATUS, tmp, sizeof(tmp)))
			status = atoi(tmp);

		if (0 == last_druleid || drule.druleid != last_druleid)
		{
			result = DBselect(
					"select dcheckid"
					" from dchecks"
					" where druleid=" ZBX_FS_UI64
						" and uniq=1",
					drule.druleid);

			if (NULL != (row = DBfetch(result)))
			{
				ZBX_STR2UINT64(drule.unique_dcheckid, row[0]);
			}
			DBfree_result(result);

			last_druleid = drule.druleid;
		}

		if ('\0' == *last_ip || 0 != strcmp(ip, last_ip))
		{
			memset(&dhost, 0, sizeof(dhost));
			strscpy(last_ip, ip);
		}

		zabbix_log(LOG_LEVEL_DEBUG, "%s() druleid:" ZBX_FS_UI64 " dcheckid:" ZBX_FS_UI64 " unique_dcheckid:"
				ZBX_FS_UI64 " type:%d time:'%s %s' ip:'%s' dns:'%s' port:%d key:'%s' value:'%s'",
				__function_name, drule.druleid, dcheck.dcheckid, drule.unique_dcheckid, dcheck.type,
				zbx_date2str(itemtime), zbx_time2str(itemtime), ip, dns, port, dcheck.key_, value);

		DBbegin();
		if (-1 == dcheck.type)
			discovery_update_host(&dhost, ip, status, itemtime);
		else
			discovery_update_service(&drule, &dcheck, &dhost, ip, dns, port, status, value, itemtime);
		DBcommit();

		continue;
json_parse_error:
		zabbix_log(LOG_LEVEL_WARNING, "invalid discovery data: %s", zbx_json_strerror());
	}
exit:
	if (SUCCEED != ret)
		zabbix_log(LOG_LEVEL_WARNING, "invalid discovery data: %s", zbx_json_strerror());

	zabbix_log(LOG_LEVEL_DEBUG, "End of %s():%s", __function_name, zbx_result_string(ret));
}

/******************************************************************************
 *                                                                            *
 * Function: process_areg_data                                                *
 *                                                                            *
 * Purpose: update auto-registration data, received from proxy                *
 *                                                                            *
 * Author: Alexander Vladishev                                                *
 *                                                                            *
 ******************************************************************************/
void	process_areg_data(struct zbx_json_parse *jp, zbx_uint64_t proxy_hostid)
{
	const char		*__function_name = "process_areg_data";

	struct zbx_json_parse	jp_data, jp_row;
	int			ret;
	const char		*p = NULL;
	time_t			now, hosttime, itemtime;
	char			host[HOST_HOST_LEN_MAX], ip[INTERFACE_IP_LEN_MAX],
				dns[INTERFACE_DNS_LEN_MAX], tmp[MAX_STRING_LEN];
	unsigned short		port;

	zabbix_log(LOG_LEVEL_DEBUG, "In %s()", __function_name);

	now = time(NULL);

	if (SUCCEED != (ret = zbx_json_value_by_name(jp, ZBX_PROTO_TAG_CLOCK, tmp, sizeof(tmp))))
		goto exit;

	if (SUCCEED != (ret = zbx_json_brackets_by_name(jp, ZBX_PROTO_TAG_DATA, &jp_data)))
		goto exit;

	hosttime = atoi(tmp);

	while (NULL != (p = zbx_json_next(&jp_data, p)))
	{
		if (FAIL == (ret = zbx_json_brackets_open(p, &jp_row)))
			break;

		if (FAIL == zbx_json_value_by_name(&jp_row, ZBX_PROTO_TAG_CLOCK, tmp, sizeof(tmp)))
			goto json_parse_error;
		itemtime = now - (hosttime - atoi(tmp));

		if (FAIL == zbx_json_value_by_name(&jp_row, ZBX_PROTO_TAG_HOST, host, sizeof(host)))
			goto json_parse_error;

		if (FAIL == zbx_json_value_by_name(&jp_row, ZBX_PROTO_TAG_IP, ip, sizeof(ip)))
			*ip = '\0';

		if (FAIL == zbx_json_value_by_name(&jp_row, ZBX_PROTO_TAG_DNS, dns, sizeof(dns)))
			*dns = '\0';

		if (FAIL == zbx_json_value_by_name(&jp_row, ZBX_PROTO_TAG_PORT, tmp, sizeof(tmp)))
			*tmp = '\0';

		if (FAIL == is_ushort(tmp, &port))
			port = ZBX_DEFAULT_AGENT_PORT;

		DBbegin();
		DBregister_host(proxy_hostid, host, ip, dns, port, itemtime);
		DBcommit();

		continue;
json_parse_error:
		zabbix_log(LOG_LEVEL_WARNING, "invalid auto registration data: %s", zbx_json_strerror());
	}
exit:
	if (SUCCEED != ret)
		zabbix_log(LOG_LEVEL_WARNING, "invalid auto registration data: %s", zbx_json_strerror());

	zabbix_log(LOG_LEVEL_DEBUG, "End of %s():%s", __function_name, zbx_result_string(ret));
}
