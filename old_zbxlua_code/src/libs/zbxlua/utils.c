#include "utils.h"

#include "log.h"
#include "common.h"
#include "lua_uint64_lib.h"

#include "lauxlib.h"

#define USERID_KEY "userid"

/******************************************************************************
 *                                                                            *
 * Function: __lua_error                                                      *
 *                                                                            *
 * Purpose: Wrapper for luaL_error and zabbix_log                             *
 *                                                                            *
 * Parameters: L (Lua state to use), format string, parameters                *
 *                                                                            *
 * Return value:                                                              *
 *                                                                            *
 * Author: Andrew Nelson                                                      *
 *                                                                            *
 * Comments:                                                                  *
 *                                                                            *
 ******************************************************************************/
void	lua_zbx_error(lua_State *L, const char *fmt, ...)
{
	va_list	args;
	char	*str = NULL;

	va_start(args, fmt);
	str = zbx_dvsprintf(str, fmt, args);
	va_end(args);

	zabbix_log(LOG_LEVEL_WARNING, "Lua error: %s", str);
	luaL_error(L, str);

	zbx_free(str);
}

void	set_current_userid(lua_State *L, zbx_uint64_t userid)
{
	lua_pushstring(L, USERID_KEY);
	lua_pushuint64(L, userid);
	lua_settable(L, LUA_REGISTRYINDEX);
}

zbx_uint64_t get_current_userid(lua_State *L)
{
	lua_pushstring(L, USERID_KEY);
	lua_gettable(L, LUA_REGISTRYINDEX);
	return lua_touint64(L, -1);
}
