#ifndef ZABBIX_CHECKS_LUA_H
#define ZABBIX_CHECKS_LUA_H

#include "common.h"
#include "dbcache.h"
#include "sysinfo.h"

#include "lua.h"

int get_lua_item(lua_State *L, DC_ITEM *item, AGENT_RESULT *result);
#endif
