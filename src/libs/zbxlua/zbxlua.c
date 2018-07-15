/*
 * ** ZABBIX
 * ** Copyright (C) 2000-2005 SIA Zabbix
 * **
 * ** This program is free software; you can redistribute it and/or modify
 * ** it under the terms of the GNU General Public License as published by
 * ** the Free Software Foundation; either version 2 of the License, or
 * ** (at your option) any later version.
 * **
 * ** This program is distributed in the hope that it will be useful,
 * ** but WITHOUT ANY WARRANTY; without even the implied warranty of
 * ** MERCHANTABILITY or FITNESS FOR A PARTICULAR PURPOSE.  See the
 * ** GNU General Public License for more details.
 * **
 * ** You should have received a copy of the GNU General Public License
 * ** along with this program; if not, write to the Free Software
 * ** Foundation, Inc., 675 Mass Ave, Cambridge, MA 02139, USA.
 * **/

#include <time.h>

#include "lua.h"
#include "lualib.h"
#include "lauxlib.h"

#include "log.h"

#include "zbxlua.h"
#include "lua_cfg.h"
#include "lua_zbxitem_lib.h"
#include "utils.h"

typedef struct lua_global_int {
	const char *name;
	int val;
} lua_global_int;

typedef struct lua_global_str {
	const char *name;
	const char *val;
} lua_global_str;

/* list of safe calles for evaluating items */
static const char	*safe_calles[] = {
	"LOG_LEVEL_DEBUG",
	"LOG_LEVEL_WARNING",
	"LOG_LEVEL_ERR",
	"LOG_LEVEL_CRIT",
	"LOG_LEVEL_EMPTY",
	"ZBX_FLAG_SEC",
	"ZBX_FLAG_VALUES",
	"ZABBIX_VERSION",
	"now",
	"zabbix_log",
	"Item",
	"uint64",
	"Util",
	/* lua internal functions */
	"tostring",
	"unpack",
	"next",
	"tonumber",
	"_VERSION",
	"type",
	"pairs",
	"ipairs",
	"select",
	"error",
	"xpcall",
	NULL
};

/******************************************************************************
 *                                                                            *
 * Function: lua_now                                                          *
 *                                                                            *
 * Purpose: Pushes current time (in time_t since epoch) to the Lua stack      *
 *                                                                            *
 * Parameters: L (Lua state to use)                                           *
 *                                                                            *
 * Return value: int, 1 (number of values put on Lua stack)                   *
 *                                                                            *
 * Author: Andrew Nelson                                                      *
 *                                                                            *
 * Comments:                                                                  *
 *                                                                            *
 ******************************************************************************/
static int	lua_now(lua_State *L)
{
	time_t	seconds;

	seconds = time(NULL);
	lua_pushnumber(L, seconds);

	return 1;
}

/******************************************************************************
 *                                                                            *
 * Function: lua_zabbix_log                                                   *
 *                                                                            *
 * Purpose: C function wrapper for Lua to zabbix_log                          *
 *                                                                            *
 * Parameters: L (Lua state to use)                                           *
 *                                                                            *
 * Return value: int, 0 (number of values put on Lua stack)                   *
 *                                                                            *
 * Author: Andrew Nelson                                                      *
 *                                                                            *
 * Comments: the type checks are not necessary, lua_to* will always return    *
 *           sane values                                                      *
 *                                                                            *
 ******************************************************************************/
static int	lua_zabbix_log(lua_State* L)
{
	int		level;
	const char	*s;

	level = lua_tointeger(L, 1);
	s = lua_tostring(L, 2);

	zabbix_log(level, "lua_log: %s", s);

	return 0;
}


/* end of zabbix lua API */

static void	add_parameters(lua_State *L, const char *params)
{
	int	argc, i;
	char	param[MAX_STRING_LEN];

	argc = num_param(params);
	lua_newtable(L);

	for (i = 1; i <= argc; i++)
	{
		get_param(params, i, param, MAX_STRING_LEN);
		lua_pushnumber(L, i);
		lua_pushstring(L, param);
		lua_settable(L, -3);
	}
}

/* prepare sand_box environment */
static void    prepare_env(lua_State *L, const DC_ITEM *item, const char *params, const int index)
{
	const char	**calle;

	lua_newtable(L);

	lua_pushstring(L, "zbx_host");
	lua_pushstring(L, item->host.host);
	lua_settable(L, -3);

	lua_pushstring(L, "zbx_hostid");
	lua_pushnumber(L, item->host.hostid);
	lua_settable(L, -3);

	lua_pushstring(L, "ARGV");
	add_parameters(L, params);
	lua_settable(L, -3);

	for (calle = safe_calles; *calle != NULL; calle++)
	{
		lua_pushstring(L, *calle);
		lua_getglobal(L, *calle);
		lua_settable(L, -3);
	}

	lua_setfenv(L, index - 1); /* setup sandbox environment for loaded code */
}

static int ensure_proper_return_type(lua_State *L, const DC_ITEM *item)
{
	int 	retval = SUCCEED;

	switch (item->value_type)
	{
		case ITEM_VALUE_TYPE_FLOAT:
			if (lua_isuint64(L, -1))
			{
				lua_pushnumber(L, lua_touint64(L, -1));
			}
			else if (!(lua_isnumber(L,-1) || lua_isuint64(L, -1)))
			{
				lua_pushstring(L, "Invalid return type, expected uint64 or number");
				retval = FAIL;
			}
			break;
		case ITEM_VALUE_TYPE_UINT64:
			if (!(lua_isnumber(L,-1) || lua_isuint64(L, -1)))
			{
				lua_pushstring(L, "Invalid return type, expected uint64 or number");
				retval = FAIL;
			}
			break;
		case ITEM_VALUE_TYPE_STR:
		case ITEM_VALUE_TYPE_LOG:
		case ITEM_VALUE_TYPE_TEXT:
			if (!(lua_isstring(L,-1) || lua_isnumber(L, -1)))
			{
				lua_pushstring(L, "Invalid return type, expected string or number");
				retval = FAIL;
			}
	}

	return retval;
}

static int	newindex_error(lua_State *L)
{
	luaL_error(L, "attempt to update a read-only table");
	return 0;
}

/* see http://www.lua.org/pil/13.4.5.html */
static void 	make_table_readonly(lua_State *L, const char *name)
{
	lua_newtable(L);          /* proxy = {} */
	lua_newtable(L);          /* mt = {} */

	lua_pushstring(L, "__newindex");
	lua_pushcfunction(L, newindex_error);
	lua_rawset(L, -3);        /* mt.__newindex = lua_newindex_error */

	lua_pushstring(L, "__index");
	lua_getglobal(L, name);
	lua_rawset(L, -3);        /* mt.__index = name */

	lua_setmetatable(L, -2);  /* setmetatable(proxy, mt) */
	lua_setglobal(L, name);   /* name = proxy */
}

/* returns 0 if default user can be found */
static zbx_uint64_t	get_default_userid()
{
	const char	*_name = "get_default_userid";
	DB_RESULT	result;
	DB_ROW		row;
	zbx_uint64_t	userid = 0;

	result = DBselect("select userid from users where alias='%s'", CONFIG_LUA_DEFAULT_USER);

	if (NULL != (row = DBfetch(result)) && NULL != row[0])
		ZBX_STR2UINT64(userid, row[1]);

	DBfree_result(result);

	if (userid == 0)
	{
		zabbix_log(LOG_LEVEL_WARNING, "In %s: Could not find user %s",
			       _name, CONFIG_LUA_DEFAULT_USER);
		return FAIL;
	}

	return userid;
}

static int	setup_userid(lua_State *L, const DC_ITEM *item)
{
	const char	*_name = "setup_userid";
	zbx_uint64_t	userid = 0;

	/* there should be stored userid in item->username, if not use the default */
	if (item->username_orig == NULL || strcmp(item->username_orig, "") == 0)
	{
		zabbix_log(LOG_LEVEL_DEBUG,
				"[%s] Username not given for Lua script, using default: %s",
				_name, CONFIG_LUA_DEFAULT_USER);
		userid = get_default_userid();
	}
	else
	{
		ZBX_STR2UINT64(userid, item->username_orig);

		if (userid == 0)
		{
			zabbix_log(LOG_LEVEL_WARNING, "In %s: Invalid userid specified %s", _name, item->username);
			userid = get_default_userid();
		}

	}

	if (userid == 0)
	{
		zabbix_log(LOG_LEVEL_WARNING, "In %s: unable to determine runtime user for item: "ZBX_FS_UI64,
				_name, item->itemid);
		return FAIL;
	}

	set_current_userid(L, userid);
	zabbix_log(LOG_LEVEL_DEBUG, "[%s] Executing Lua script as userid: "ZBX_FS_UI64, _name, userid);

	return SUCCEED;
}

/******************************************************************************
 *                                                                            *
 * Function: execute_lua                                                      *
 *                                                                            *
 * Purpose: Executes the Lua script given by item                             *
 *                                                                            *
 * Parameters: L (Lua state to use), item                                     *
 *                                                                            *
 * Return value: int, returns the result of the lua call, any results of the  *
 *               Lua call itself will be available on the Lua stack           *
 *                                                                            *
 * Author: Andrew Nelson                                                      *
 *                                                                            *
 * Comments:                                                                  *
 *                                                                            *
 ******************************************************************************/
int	execute_lua(lua_State *L, const char *params, DC_ITEM *item)
{
	const char	*_name = "execute_lua";
	int		retval;

	lua_settop(L, 0);  /* force some garbage collection */

	if (FAIL == setup_userid(L, item))
		return FAIL;

	if (luaL_loadstring(L, item->params_orig))
		return FAIL;

	prepare_env(L, item, params, -1);

	zabbix_log(LOG_LEVEL_DEBUG, "[%s] Received parameters: %s", _name,params);
	zabbix_log(LOG_LEVEL_DEBUG, "[%s] Executing: %s", _name, item->params_orig);

	retval = lua_pcall(L, 0, LUA_MULTRET, 0);  /* 0 == SUCCEEED */

	zabbix_log(LOG_LEVEL_DEBUG, "[%s] Retval from luaL_dostring: %s", _name, retval == 0 ? "SUCCESS" : "FAIL");

	/* if retval != 0 error msg is on the stack already */
	retval = (retval == 0) ? ensure_proper_return_type(L, item) : FAIL;

	return retval;
}

/******************************************************************************
 *                                                                            *
 * Function: init_lua_env                                                     *
 *                                                                            *
 * Purpose: Initialize the Lua environment and set it up with the appropriate *
 *          basic Zabbix functions and variables.                             *
 *                                                                            *
 * Parameters:                                                                *
 *                                                                            *
 * Return value: Initialized Lua state                                        *
 *                                                                            *
 * Author: Andrew Nelson                                                      *
 *                                                                            *
 * Comments:                                                                  *
 *                                                                            *
 ******************************************************************************/

lua_State	*init_lua_env()
{
	const luaL_reg		*func;
	const lua_global_int	*globalint;
	const lua_global_str	*globalstr;
	int			result;
	lua_State		*L;

	/* setup a table to make it easier to register functions */
	static const luaL_reg luafunctions[] =
	{
		{ "zabbix_log",				lua_zabbix_log },
		{ "now",				lua_now },
		{ NULL }
	};

	/* setup a table to make it easier to register globals */

	static const lua_global_int luaglobalint[] =
	{
		{ "LOG_LEVEL_EMPTY",		LOG_LEVEL_EMPTY },
		{ "LOG_LEVEL_CRIT",		LOG_LEVEL_CRIT },
		{ "LOG_LEVEL_ERR",		LOG_LEVEL_ERR },
		{ "LOG_LEVEL_WARNING",		LOG_LEVEL_WARNING },
		{ "LOG_LEVEL_DEBUG",		LOG_LEVEL_DEBUG },
		{ "LOG_LEVEL_DEBUG",		LOG_LEVEL_DEBUG },
		{ "ZBX_FLAG_VALUES",		ZBX_FLAG_VALUES },
		{ "ZBX_FLAG_SEC",		ZBX_FLAG_SEC },
		{ NULL }
	};

	static const lua_global_str luaglobalstr[] =
	{
		{ "ZABBIX_VERSION",		VERSION },
		{ NULL }
	};

	L = lua_open();

	luaopen_base(L);
	luaopen_uint64(L);
	luaopen_zbxitem(L);

	for (globalint = luaglobalint; globalint->name != NULL; globalint++)
	{
		lua_pushinteger(L, globalint->val);
		lua_setglobal(L, globalint->name);
	}

	for (globalstr = luaglobalstr; globalstr->name != NULL; globalstr++)
	{
		lua_pushstring(L, globalstr->val);
		lua_setglobal(L, globalstr->name);
	}

	for (func = luafunctions; func->func != NULL; func++)
	{
		lua_register(L, func->name, func->func);
	}

	lua_newtable(L);
	lua_setglobal(L, "Util");

	if (CONFIG_LUA_SCRIPT_LIBRARY != NULL)
	{
		set_current_userid(L, 1); /* run under first user in the system, aka Admin */

		if ((result = luaL_dofile(L, CONFIG_LUA_SCRIPT_LIBRARY)) != 0)
			zabbix_log(LOG_LEVEL_ERR, "Error loading Lua library %s, error #%d  msg:%s",
					CONFIG_LUA_SCRIPT_LIBRARY, result, lua_tostring(L, -1));

	}

	set_current_userid(L, get_default_userid()); /* sane default value */

	make_table_readonly(L, "Util");
	make_table_readonly(L, "Item");

	lua_settop(L, 0);

	return L;
}

