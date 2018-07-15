#ifndef ZABBIX_ZBX_LUA_H
#define ZABBIX_ZBX_LUA_H

#include "dbcache.h"
#include "zbxtypes.h"
#include "lua_uint64_lib.h"

int execute_lua(lua_State* L, const char* params, DC_ITEM *item);
lua_State *init_lua_env();

#endif
