#include "lua.h"
#include "lauxlib.h"

#include "db.h"
#include "log.h"
#include "zbxserver.h"

#include "lua_cfg.h"
#include "lua_uint64_lib.h"

#include "utils.h"

#define ITEM_METATABLE_KEY "ZbxItem"

static const char	*zbx_evaluators[] = {
	"eval_logeventid",
	"eval_logsource",
	"eval_logseverity",
	"eval_count",
	"eval_sum",
	"eval_avg",
	"eval_last",
	"eval_min",
	"eval_max",
	"eval_delta",
	"eval_nodata",
	"eval_abschange",
	"eval_change",
	"eval_diff",
	"eval_str",
	"eval_strlen",
	"eval_fuzzytime"
};

/******************************************************************************
 *                                                                            *
 * Function: check_hostid_read_permission                                     *
 *                                                                            *
 * Purpose: check if user with userid is able to access host information      *
 *                                                                            *
 * Parameters:                                                                *
 *                                                                            *
 * Return value:  FAIL - access denied, SUCCED - access granted               *
 *                                                                            *
 * Author: Andrew Nelson                                                      *
 *                                                                            *
 * Comments:                                                                  *
 ******************************************************************************/
static int	check_hostid_read_permission(const zbx_uint64_t userid, zbx_uint64_t hostid)
{
	const char	*_name = "check_hostid_read_permission";
	int		retval;

	if (CONFIG_LUA_CHECK_PERMISSIONS == 0)
	{
		zabbix_log(LOG_LEVEL_DEBUG, "Permissions check skipped");
		return SUCCEED;
	}

	zabbix_log(LOG_LEVEL_DEBUG, "In %s "ZBX_FS_UI64":"ZBX_FS_UI64, _name, userid, hostid);

	retval = (PERM_DENY == get_host_permission(userid, hostid)) ? FAIL : SUCCEED;

	zabbix_log(LOG_LEVEL_DEBUG, "End of %s: %s", (retval ? "TRUE" : "FAIL"));

	return retval;
}

static void	deep_copy_db_item(DB_ITEM *out_item, DB_ITEM *in_item)
{
	memcpy(out_item, in_item, sizeof(DB_ITEM));

	out_item->key          = strdup(in_item->key);
	out_item->host_name    = strdup(in_item->host_name);

	if (in_item->lastvalue[0])
		out_item->lastvalue[0] = strdup(in_item->lastvalue[0]);

	if (in_item->lastvalue[1])
		out_item->lastvalue[1] = strdup(in_item->lastvalue[1]);

	out_item->units        = strdup(in_item->units);
	out_item->formula      = strdup(in_item->formula);

	if ((in_item->prevorgvalue_null != 1) &&
		(in_item->value_type != ITEM_VALUE_TYPE_FLOAT) &&
		(in_item->value_type != ITEM_VALUE_TYPE_UINT64))
	{
		out_item->prevorgvalue.str = strdup(in_item->prevorgvalue.str);
	}
}

/* alocate space of userdatum */
static int	item_new(lua_State *L, DB_ITEM *db_item)
{
	DB_ITEM		*item;

	item = (DB_ITEM*)lua_newuserdata(L, sizeof(DB_ITEM));
	deep_copy_db_item(item, db_item);

	luaL_getmetatable(L, ITEM_METATABLE_KEY);
	lua_setmetatable(L, -2);

	return 1;
}

/* find the blody DC_ITEM */
/* can be only called from lua (asuming first argument at 1 on private stack) */
static int	item_find(lua_State *L)
{
	const char	*_name = "lua: Item.item_find";
	zbx_uint64_t	itemid, nodeid;
	DB_ITEM		item;
	DB_RESULT	result;
	DB_ROW		row;
	char		*esc_key = NULL, *esc_host = NULL;
	int		ret;
	const char	*host, *key;


	if (lua_isnumber(L, 1) || lua_isuint64(L, 1)) /* itemid, <> */
	{
		itemid = lua_touint64(L, 1);

		if (lua_gettop(L) > 2) /* itemid, nodeid */
		{
			if (lua_isnumber(L, 2) || lua_isuint64(L, 2))
			{
				nodeid = lua_touint64(L, 2);
				/* nodeids are 14 digits to the left */
				itemid = nodeid * (zbx_uint64_t)__UINT64_C(100000000000000) + itemid;
			}
			/* silently ignore everything else */
		}

		result = DBselect("select %s where i.itemid='%d'", ZBX_SQL_ITEM_SELECT, itemid);
	}
	else if (lua_isstring(L, 1)) /* hostname, key */
	{
		if (!lua_isstring(L, 2))
			luaL_argerror(L, 2, "expected item_key={string}");

		host = lua_tostring(L, 1);
		key  = lua_tostring(L, 2);

		zabbix_log(LOG_LEVEL_DEBUG, "[%s] Received (host,key) (%s,%s)", _name,
				host, key);

		esc_host = DBdyn_escape_string(host);
		esc_key  = DBdyn_escape_string(key);

		result = DBselect("select %s where h.hostid=i.hostid and h.name='%s' and i.key_='%s' limit 1",
				ZBX_SQL_ITEM_SELECT, esc_host, esc_key);

		zbx_free(esc_host);
		zbx_free(esc_key);
	}
	else
	{
		luaL_argerror(L, 1, "expected itemid={number|uint64} or hostname={string}");
	}

	if (NULL == (row = DBfetch(result)))
	{
		DBfree_result(result);
		return 0; /* nil */
	}

	DBget_item_from_db(&item, row);

	if (check_hostid_read_permission(get_current_userid(L), item.hostid) != SUCCEED)
	{
		DBfree_result(result);

		zabbix_log(LOG_LEVEL_WARNING, "[%s] current user doesn't have permission for (host,key) (%s,%s)",
				_name, host, key);
		return 0; /* will not return lua error here, it would tell that item exist */
	}

	ret = item_new(L, &item);

	DBfree_result(result);

	return ret;
}

DB_ITEM	*lua_tozbxitem(lua_State *L, int narg)
{
	void	*ud = luaL_checkudata(L, narg, ITEM_METATABLE_KEY);
	luaL_argcheck(L, ud != NULL, 1, "`Item' expected");
	return (DB_ITEM *)ud;
}

static int	item_gc(lua_State *L)
{
	DB_ITEM		*item;

	item = lua_tozbxitem(L, 1);

	zbx_free(item->key);
	zbx_free(item->host_name);
	zbx_free(item->lastvalue[0]);
	zbx_free(item->lastvalue[1]);
	zbx_free(item->units);
	zbx_free(item->formula);

	if ((item->prevorgvalue_null != 1) &&
		(item->value_type != ITEM_VALUE_TYPE_FLOAT) &&
		(item->value_type != ITEM_VALUE_TYPE_UINT64))
	{
		zbx_free(item->prevorgvalue.str);
	}
	/* the rest is done by lua gc */
	return 0;
}

#define push_clock(L, str, p) { \
	p = strpbrk(str, ":"); *p = '\0'; \
	lua_pushnumber(L, atoi(str)); \
	p++; }

static int	lua_push_history(lua_State *L, const DB_ITEM *item, const int count, const int flag)
{
	const char	*_name = "lua_push_history";
	char		**h_value, **hvp, *p;
	int		function = ZBX_DB_GET_HIST_VALUE;
	int		clock_from = 0, clock_to = 0, last_n = 0;
	zbx_uint64_t	value;

	zabbix_log(LOG_LEVEL_DEBUG, "In function %s", _name);

	if (flag == ZBX_FLAG_SEC)
	{
		clock_to = time(NULL);
		clock_from = clock_to - count;
	}
	else
		last_n = count;

	h_value = DBget_history(item->itemid, item->value_type, function, clock_from,
			clock_to, NULL, "concat(clock,':',value)", last_n);

	zabbix_log(LOG_LEVEL_DEBUG, "[%s] get history", _name);

	lua_newtable(L);

	switch (item->value_type)
	{
		case ITEM_VALUE_TYPE_UINT64:
			for (hvp = h_value; NULL != *hvp; hvp++)
			{
				push_clock(L, *hvp, p);
				is_uint64(p, &value);
				lua_pushuint64(L, value);
				lua_settable(L, -3);
			}
			break;
		case ITEM_VALUE_TYPE_FLOAT:
			for (hvp = h_value; NULL != *hvp; hvp++)
			{
				push_clock(L, *hvp, p);
				lua_pushnumber(L, atof(p));
				lua_settable(L, -3);
			}
			break;
		case ITEM_VALUE_TYPE_STR:
		case ITEM_VALUE_TYPE_LOG:
		case ITEM_VALUE_TYPE_TEXT:
			for (hvp = h_value; NULL != *hvp; hvp++)
			{
				push_clock(L, *hvp, p);
				lua_pushstring(L, p);
				lua_settable(L, -3);
			}
			break;
	}

	DBfree_history(h_value);

	zabbix_log(LOG_LEVEL_DEBUG, "[%s] exiting", _name);

	return 1; /* number of items return from lua function (single table) */
}

/******************************************************************************
 *                                                                            *
 * Function: item_get_history                                                 *
 *                                                                            *
 * Purpose: C function called by Lua to retrieve an item's values             *
 *                                                                            *
 * Parameters: L (Lua state to use)                                           *
 *                                                                            *
 * Return value:  int, the number of results placed on the stack              *
 *                                                                            *
 * Author: Andrew Nelson                                                      *
 *                                                                            *
 * Comments:                                                                  *
 *           Lua function definition:                                         *
 *           item:history(count, type)                                        *
 *            - count is the number of seconds or values                      *
 *            - type: optional (default to ZBX_FLAG_SEC)                      *
 *                 ZBX_FLAG_SEC    - consider count as seconds                *
 *                 ZBX_FLAG_VALUES - consider count as number of values       *
 *                                                                            *
 ******************************************************************************/
static int	item_get_history(lua_State* L)
{
	const char	*_name = "lua_get_history";
	int		count, flag = ZBX_FLAG_SEC;
	const DB_ITEM	*item;

	item = lua_tozbxitem(L, 1);

	if (lua_gettop(L) < 2)
	{
		zabbix_log(LOG_LEVEL_ERR, "%s Did not recieve enough values", _name);
		lua_zbx_error(L, "One or more arguments expected");
	}

	if (lua_gettop(L) > 2)
	{
		if (lua_isnumber(L, 3))
			flag = lua_tointeger(L, 3);
		else
			zabbix_log(LOG_LEVEL_WARNING, "[%s] expected to receive a number ZBX_SEC or ZBX_NUM: %s",
					_name, lua_tostring(L, 3));
	}

	if (lua_isnumber(L, 2))
		count = lua_tointeger(L,2);
	else
		lua_zbx_error(L, "[%s] expected to receive a number for clock.  received: %s",
				_name, lua_tostring(L, -1));

	return lua_push_history(L, item, count, flag);
}

/* getters */
static int	item_get_itemid(lua_State *L)
{
	const DB_ITEM	*item;

	item = lua_tozbxitem(L, 1);
	lua_pushuint64(L, item->itemid);
	return 1;
}

static int	item_get_lastvalue(lua_State *L)
{
	const DB_ITEM	*item;
	zbx_uint64_t	value;
	const char	*last_value;

	item = lua_tozbxitem(L, 1);
	lua_pushnumber(L, item->lastclock);
	last_value = item->lastvalue[0];

	if (item->value_type == ITEM_VALUE_TYPE_UINT64)
	{
		is_uint64(last_value, &value);
		lua_pushuint64(L, value);
	}
	else if (item->value_type == ITEM_VALUE_TYPE_FLOAT)
	{
		lua_pushnumber(L, atof(last_value));
		lua_settable(L, -3);
	}
	else
	{
		/* if you want to get more then 255 chars, use get_history */
		lua_pushstring(L, last_value);
	}

	return 2;
}

/******************************************************************************
 *                                                                            *
 * Function: lua_zabbix_evaluate                                              *
 *                                                                            *
 * Purpose: C function wrapper for zabbix evaluators                          *
 *                                                                            *
 * Parameters: L (Lua state to use)                                           *
 *                                                                            *
 * Return value: int, 0 (number of values put on Lua stack)                   *
 *                                                                            *
 * Author: Michal Humpula                                                     *
 *                                                                            *
 * Comments:                                                                  *
 *           we are using multiple lua definitions pointing to single C       *
 *           wrapper. To distinguish the name we store coresponding name in   *
 *           upvalue                                                          *
 *                                                                            *
 ******************************************************************************/
static int	lua_zabbix_evaluate(lua_State* L)
{
	const char	*_name = "lua_zabix_evaluate";
	const char	*fn_name;
	char 		*parameters, *value;
	DB_ITEM		*item;
	int		retval;
	size_t		len;

	item = lua_tozbxitem(L, 1);

	/* this points to pseudoindex so no need to worry about loosing pointer */
	fn_name = lua_tostring(L, lua_upvalueindex(1));


	if (lua_gettop(L) != 2)
	{
		lua_zbx_error(L, "%s expected 2 parameters", fn_name);
	}

	/* the second arg is a table/array or string */
	if (lua_isstring(L, 2) || lua_isnumber(L, 2))
	{
		parameters = strdup(lua_tostring(L, 2));
	}
	else if(lua_istable(L, 2))
	{
		/* FIXME: internal API of zabbix is a pain
		 * this is the second case we have to assemble string and
		 * break it to the parts later
		 */
		/*FIXME: this could be reasonable sized for buffer */
		parameters = zbx_malloc(NULL, 256);
	        *parameters = '\0';
		len = 0;

		lua_pushnil(L);
		while (lua_next(L, -2) != 0)
		{
			len = zbx_strlcat(parameters, lua_tostring(L, -1), 255);
			parameters[len] = ',';
			parameters[len + 1] = '\0';
			lua_pop(L, 1);
		}

		parameters[len] = '\0';
	}
	else
	{
		parameters = zbx_calloc(NULL, 1, sizeof(char)); /* empty string */
	}

	/* do some work finally */
	value = zbx_malloc(NULL, MAX_BUFFER_LEN);

	zabbix_log(LOG_LEVEL_DEBUG, "[%s] calling evalutate_function with"
		       "(itemid, fn_name, parameters)=("ZBX_FS_UI64", %s, %s",
			_name, item->itemid, fn_name + 5, parameters);

	/* fn_name has first for characters "eval_" so get rid of them when passing */
	retval = evaluate_function(value, item, fn_name + 5, parameters, time(NULL));

	if (retval == SUCCEED)
	{
		/* any idea why evaluate_functions is return string but always filled with number? */
		lua_pushstring(L, value);
		lua_Number num = lua_tonumber(L, -1);
		lua_pop(L, 1);
		lua_pushnumber(L, num);
	}
	else
	{
		lua_pushnil(L);
	}

	zbx_free(parameters);
	zbx_free(value);

	return 1;
}

/* C-API side */
static const struct luaL_reg zbxitem_meths[] = {
	{ "itemid", item_get_itemid },
	{ "last_value", item_get_lastvalue },
	{ "history", item_get_history },
	{ "__gc", item_gc },
	{ NULL, NULL }
};

static const struct luaL_reg zbxitem_funcs[] = {
	{ "new", item_find },
	{ "find", item_find },
	{ NULL, NULL }
};

int	luaopen_zbxitem(lua_State *L)
{
	const char	**fn_name;

	luaL_newmetatable(L, ITEM_METATABLE_KEY);

	/* metatable.__index = metatable
	 * so we can the call item:history(200) instead of imte.history(item, 200)
	 */
	lua_pushliteral(L, "__index");
	lua_pushvalue(L, -2);
	lua_rawset(L, -3);

	luaL_register(L, NULL, zbxitem_meths);

	/* register evaluator functions */
	for (fn_name = zbx_evaluators; *fn_name != NULL; fn_name++)
	{
		lua_pushstring(L, *fn_name);                  /* first upvalue, fn name */
		lua_pushcclosure(L, &lua_zabbix_evaluate, 1); /* create closure */
		lua_setfield(L, -2, *fn_name);                /* add fn to metatable */
	}

	luaL_register(L, "Item", zbxitem_funcs);

	return 1;
}

