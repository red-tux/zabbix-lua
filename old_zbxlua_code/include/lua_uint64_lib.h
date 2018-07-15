#ifndef LUA_UINT64_LIB_H
#define LUA_UINT64_LIB_H

#include <lua.h>
#include "common.h"
#include "zbxtypes.h"

int	lua_isuint64(lua_State *L, int index);
zbx_uint64_t	lua_touint64(lua_State *L, int index);
int	lua_pushuint64(lua_State *L, zbx_uint64_t val);
int	lua_uint64_tostring(lua_State *L);

int	luaopen_uint64(lua_State *L);
#endif
