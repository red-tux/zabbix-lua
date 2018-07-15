#ifndef ZBXLUA_UTILS_H
#define ZBXLUA_UTILS_H

#include "lua.h"
#include "common.h"

enum {
	ZBX_FLAG_SEC = 0,
	ZBX_FLAG_VALUES
} zbx_count_flag;

void lua_zbx_error(lua_State *L, const char *fmt, ...);

void set_current_userid(lua_State *L, const zbx_uint64_t userid);
zbx_uint64_t get_current_userid(lua_State *L);

#endif
