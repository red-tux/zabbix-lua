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
#include "checks_internal.h"
#include "checks_java.h"
#include "log.h"
#include "dbcache.h"
#include "zbxself.h"

/******************************************************************************
 *                                                                            *
 * Function: get_value_internal                                               *
 *                                                                            *
 * Purpose: retrieve data from Zabbix server (internally supported items)     *
 *                                                                            *
 * Parameters: item - item we are interested in                               *
 *                                                                            *
 * Return value: SUCCEED - data successfully retrieved and stored in result   *
 *                         and result_str (as string)                         *
 *               NOTSUPPORTED - requested item is not supported               *
 *                                                                            *
 * Author: Alexei Vladishev                                                   *
 *                                                                            *
 * Comments:                                                                  *
 *                                                                            *
 ******************************************************************************/
int	get_value_internal(DC_ITEM *item, AGENT_RESULT *result)
{
	zbx_uint64_t	i;
	char		params[MAX_STRING_LEN], *error = NULL;
	char		tmp[MAX_STRING_LEN], tmp1[HOST_HOST_LEN_MAX];
	int		nparams, lastaccess;

	init_result(result);

	if (0 != strncmp(item->key, "zabbix[", 7))
		goto notsupported;

	if (parse_command(item->key, NULL, 0, params, sizeof(params)) != 2)
		goto notsupported;

	if (get_param(params, 1, tmp, sizeof(tmp)) != 0)
		goto notsupported;

	nparams = num_param(params);

	if (0 == strcmp(tmp, "triggers"))		/* zabbix["triggers"] */
	{
		if (1 != nparams)
			goto notsupported;

		i = DBget_row_count("triggers");
		SET_UI64_RESULT(result, i);
	}
	else if (0 == strcmp(tmp, "items"))		/* zabbix["items"] */
	{
		if (1 != nparams)
			goto notsupported;

		i = DBget_row_count("items");
		SET_UI64_RESULT(result, i);
	}
	else if (0 == strcmp(tmp, "items_unsupported"))	/* zabbix["items_unsupported"] */
	{
		if (1 != nparams)
			goto notsupported;

		i = DBget_items_unsupported_count();
		SET_UI64_RESULT(result, i);
	}
	else if (0 == strcmp(tmp, "history") ||			/* zabbix["history"] */
			0 == strcmp(tmp, "history_log") ||	/* zabbix["history_log"] */
			0 == strcmp(tmp, "history_str") ||	/* zabbix["history_str"] */
			0 == strcmp(tmp, "history_text") ||	/* zabbix["history_text"] */
			0 == strcmp(tmp, "history_uint"))	/* zabbix["history_uint"] */
	{
		if (1 != nparams)
			goto notsupported;

		i = DBget_row_count(tmp);
		SET_UI64_RESULT(result, i);
	}
	else if (0 == strcmp(tmp, "trends") ||			/* zabbix["trends"] */
			0 == strcmp(tmp, "trends_uint"))	/* zabbix["trends_uint"] */
	{
		if (1 != nparams)
			goto notsupported;

		i = DBget_row_count(tmp);
		SET_UI64_RESULT(result, i);
	}
	else if (0 == strcmp(tmp, "queue"))		/* zabbix["queue",<from>,<to>] */
	{
		int	from = 6, to = (-1);

		if (nparams > 3)
			goto notsupported;

		if (nparams >= 2)
		{
			if (get_param(params, 2, tmp, sizeof(tmp)) != 0)
				goto notsupported;
			else if (*tmp != '\0' && is_uint_prefix(tmp) == FAIL)
			{
				error = zbx_strdup(error, "Second parameter is badly formatted");
				goto notsupported;
			}
			else
				from = (*tmp != '\0' ? str2uint(tmp) : 6);
		}

		if (nparams >= 3)
		{
			if (get_param(params, 3, tmp, sizeof(tmp)) != 0)
				goto notsupported;
			else if (*tmp != '\0' && is_uint_prefix(tmp) == FAIL)
			{
				error = zbx_strdup(error, "Third parameter is badly formatted");
				goto notsupported;
			}
			else
				to = (*tmp != '\0' ? str2uint(tmp) : -1);
		}

		if (from > to && -1 != to)
		{
			error = zbx_strdup(error, "Parameters represent an invalid interval");
			goto notsupported;
		}

		i = DBget_queue_count(from, to);
		SET_UI64_RESULT(result, i);
	}
	else if (0 == strcmp(tmp, "requiredperformance"))	/* zabbix["requiredperformance"] */
	{
		if (1 != nparams)
			goto notsupported;

		SET_DBL_RESULT(result, DBget_requiredperformance());
	}
	else if (0 == strcmp(tmp, "uptime"))		/* zabbix["uptime"] */
	{
		if (1 != nparams)
			goto notsupported;

		i = time(NULL) - CONFIG_SERVER_STARTUP_TIME;
		SET_UI64_RESULT(result, i);
	}
	else if (0 == strcmp(tmp, "boottime"))		/* zabbix["boottime"] */
	{
		if (1 != nparams)
			goto notsupported;

		i = CONFIG_SERVER_STARTUP_TIME;
		SET_UI64_RESULT(result, i);
	}
	else if (0 == strcmp(tmp, "proxy"))		/* zabbix["proxy",<hostname>,"lastaccess"] */
	{
		if (3 != nparams)
			goto notsupported;

		if (get_param(params, 2, tmp1, sizeof(tmp1)) != 0)
			goto notsupported;

		if (get_param(params, 3, tmp, sizeof(tmp)) != 0)
			goto notsupported;

		if (0 == strcmp(tmp, "lastaccess"))
		{
			if (FAIL == DBget_proxy_lastaccess(tmp1, &lastaccess, &error))
				goto notsupported;
		}
		else
			goto notsupported;

		SET_UI64_RESULT(result, lastaccess);
	}
	else if (0 == strcmp(tmp, "java"))		/* zabbix["java",...] */
	{
		int	res;

		alarm(CONFIG_TIMEOUT);
		res = get_value_java(ZBX_JAVA_GATEWAY_REQUEST_INTERNAL, item, result);
		alarm(0);

		if (SUCCEED != res)
			goto notsupported;
	}
	else if (0 == strcmp(tmp, "process"))		/* zabbix["process",<type>,<mode>,<state>] */
	{
		unsigned char	process_type;
		int		process_forks;
		double		value;

		if (nparams > 4)
		{
			error = zbx_strdup(error, "Too many parameters");
			goto notsupported;
		}

		if (0 != get_param(params, 2, tmp, sizeof(tmp)))
		{
			error = zbx_strdup(error, "Required second parameter missing");
			goto notsupported;
		}

		for (process_type = 0; process_type < ZBX_PROCESS_TYPE_COUNT; process_type++)
			if (0 == strcmp(tmp, get_process_type_string(process_type)))
				break;

		if (ZBX_PROCESS_TYPE_COUNT == process_type)
		{
			error = zbx_strdup(error, "Invalid second parameter");
			goto notsupported;
		}

		process_forks = get_process_type_forks(process_type);

		if (0 != get_param(params, 3, tmp, sizeof(tmp)))
			*tmp = '\0';

		if (0 == strcmp(tmp, "count"))
		{
			if (nparams > 3)
			{
				error = zbx_strdup(error, "Too many parameters");
				goto notsupported;
			}

			SET_UI64_RESULT(result, process_forks);
		}
		else
		{
			unsigned char	aggr_func, state;
			unsigned short	process_num = 0;

			if ('\0' == *tmp || 0 == strcmp(tmp, "avg"))
				aggr_func = ZBX_AGGR_FUNC_AVG;
			else if (0 == strcmp(tmp, "max"))
				aggr_func = ZBX_AGGR_FUNC_MAX;
			else if (0 == strcmp(tmp, "min"))
				aggr_func = ZBX_AGGR_FUNC_MIN;
			else if (SUCCEED == is_ushort(tmp, &process_num) && process_num > 0)
				aggr_func = ZBX_AGGR_FUNC_ONE;
			else
			{
				error = zbx_strdup(error, "Invalid third parameter");
				goto notsupported;
			}

			if (0 == process_forks)
			{
				error = zbx_dsprintf(error, "No \"%s\" processes started",
						get_process_type_string(process_type));
				goto notsupported;
			}
			else if (process_num > process_forks)
			{
				error = zbx_dsprintf(error, "\"%s\" #%d is not started",
						get_process_type_string(process_type), process_num);
				goto notsupported;
			}

			if (0 != get_param(params, 4, tmp, sizeof(tmp)))
				*tmp = '\0';

			if ('\0' == *tmp || 0 == strcmp(tmp, "busy"))
				state = ZBX_PROCESS_STATE_BUSY;
			else if (0 == strcmp(tmp, "idle"))
				state = ZBX_PROCESS_STATE_IDLE;
			else
			{
				error = zbx_strdup(error, "Invalid fourth parameter");
				goto notsupported;
			}

			get_selfmon_stats(process_type, aggr_func, process_num, state, &value);

			SET_DBL_RESULT(result, value);
		}
	}
	else if (0 == strcmp(tmp, "wcache"))
	{
		if (nparams > 3)
			goto notsupported;

		if (get_param(params, 2, tmp, sizeof(tmp)) != 0)
			goto notsupported;

		if (get_param(params, 3, tmp1, sizeof(tmp1)) != 0)
			*tmp1 = '\0';

		if (0 == strcmp(tmp, "values"))
		{
			if ('\0' == *tmp1 || 0 == strcmp(tmp1, "all"))
				SET_UI64_RESULT(result, *(zbx_uint64_t *)DCget_stats(ZBX_STATS_HISTORY_COUNTER));
			else if (0 == strcmp(tmp1, "float"))
				SET_UI64_RESULT(result, *(zbx_uint64_t *)DCget_stats(ZBX_STATS_HISTORY_FLOAT_COUNTER));
			else if (0 == strcmp(tmp1, "uint"))
				SET_UI64_RESULT(result, *(zbx_uint64_t *)DCget_stats(ZBX_STATS_HISTORY_UINT_COUNTER));
			else if (0 == strcmp(tmp1, "str"))
				SET_UI64_RESULT(result, *(zbx_uint64_t *)DCget_stats(ZBX_STATS_HISTORY_STR_COUNTER));
			else if (0 == strcmp(tmp1, "log"))
				SET_UI64_RESULT(result, *(zbx_uint64_t *)DCget_stats(ZBX_STATS_HISTORY_LOG_COUNTER));
			else if (0 == strcmp(tmp1, "text"))
				SET_UI64_RESULT(result, *(zbx_uint64_t *)DCget_stats(ZBX_STATS_HISTORY_TEXT_COUNTER));
			else if (0 == strcmp(tmp1, "not supported"))
				SET_UI64_RESULT(result, *(zbx_uint64_t *)DCget_stats(ZBX_STATS_NOTSUPPORTED_COUNTER));
			else
				goto notsupported;
		}
		else if (0 == strcmp(tmp, "history"))
		{
			if ('\0' == *tmp1 || 0 == strcmp(tmp1, "pfree"))
				SET_DBL_RESULT(result, *(double *)DCget_stats(ZBX_STATS_HISTORY_PFREE));
			else if (0 == strcmp(tmp1, "total"))
				SET_UI64_RESULT(result, *(zbx_uint64_t *)DCget_stats(ZBX_STATS_HISTORY_TOTAL));
			else if (0 == strcmp(tmp1, "used"))
				SET_UI64_RESULT(result, *(zbx_uint64_t *)DCget_stats(ZBX_STATS_HISTORY_USED));
			else if (0 == strcmp(tmp1, "free"))
				SET_UI64_RESULT(result, *(zbx_uint64_t *)DCget_stats(ZBX_STATS_HISTORY_FREE));
			else
				goto notsupported;
		}
		else if (0 == strcmp(tmp, "trend"))
		{
			if ('\0' == *tmp1 || 0 == strcmp(tmp1, "pfree"))
				SET_DBL_RESULT(result, *(double *)DCget_stats(ZBX_STATS_TREND_PFREE));
			else if (0 == strcmp(tmp1, "total"))
				SET_UI64_RESULT(result, *(zbx_uint64_t *)DCget_stats(ZBX_STATS_TREND_TOTAL));
			else if (0 == strcmp(tmp1, "used"))
				SET_UI64_RESULT(result, *(zbx_uint64_t *)DCget_stats(ZBX_STATS_TREND_USED));
			else if (0 == strcmp(tmp1, "free"))
				SET_UI64_RESULT(result, *(zbx_uint64_t *)DCget_stats(ZBX_STATS_TREND_FREE));
			else
				goto notsupported;
		}
		else if (0 == strcmp(tmp, "text"))
		{
			if ('\0' == *tmp1 || 0 == strcmp(tmp1, "pfree"))
				SET_DBL_RESULT(result, *(double *)DCget_stats(ZBX_STATS_TEXT_PFREE));
			else if (0 == strcmp(tmp1, "total"))
				SET_UI64_RESULT(result, *(zbx_uint64_t *)DCget_stats(ZBX_STATS_TEXT_TOTAL));
			else if (0 == strcmp(tmp1, "used"))
				SET_UI64_RESULT(result, *(zbx_uint64_t *)DCget_stats(ZBX_STATS_TEXT_USED));
			else if (0 == strcmp(tmp1, "free"))
				SET_UI64_RESULT(result, *(zbx_uint64_t *)DCget_stats(ZBX_STATS_TEXT_FREE));
			else
				goto notsupported;
		}
		else
			goto notsupported;
	}
	else if (0 == strcmp(tmp, "rcache"))
	{
		if (nparams > 3)
			goto notsupported;

		if (get_param(params, 2, tmp, sizeof(tmp)) != 0)
			goto notsupported;

		if (get_param(params, 3, tmp1, sizeof(tmp1)) != 0)
			*tmp1 = '\0';

		if (0 == strcmp(tmp, "buffer"))
		{
			if ('\0' == *tmp1 || 0 == strcmp(tmp1, "pfree"))
				SET_DBL_RESULT(result, *(double *)DCconfig_get_stats(ZBX_CONFSTATS_BUFFER_PFREE));
			else if (0 == strcmp(tmp1, "total"))
				SET_UI64_RESULT(result, *(zbx_uint64_t *)DCconfig_get_stats(ZBX_CONFSTATS_BUFFER_TOTAL));
			else if (0 == strcmp(tmp1, "used"))
				SET_UI64_RESULT(result, *(zbx_uint64_t *)DCconfig_get_stats(ZBX_CONFSTATS_BUFFER_USED));
			else if (0 == strcmp(tmp1, "free"))
				SET_UI64_RESULT(result, *(zbx_uint64_t *)DCconfig_get_stats(ZBX_CONFSTATS_BUFFER_FREE));
			else
				goto notsupported;
		}
		else
			goto notsupported;
	}
	else
		goto notsupported;

	return SUCCEED;
notsupported:
	if (!ISSET_MSG(result))
	{
		if (NULL == error)
			error = zbx_strdup(error, "Internal check is not supported");

		SET_MSG_RESULT(result, error);
	}

	return NOTSUPPORTED;
}
