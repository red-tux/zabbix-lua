#include <lua.h>
#include <lauxlib.h>

#include "common.h"
#include "lua_uint64_lib.h"

/*
 * The following code was found at the following url:
 * http://codepad.org/LZpFrKbT
 * Thank you to the unknown creator.
 */

#define U64_STR_LEN 21
#define U64_LUA_TYPE "uint64"

int	lua_isuint64(lua_State *L, int index)
{
	return (lua_isuserdata(L, index) && luaL_checkudata(L, index, U64_LUA_TYPE));
}

/* get value of Integer userdata or Lua number at index, or die */
zbx_uint64_t	lua_touint64(lua_State *L, int index) {
	if (lua_isuint64(L, index))
		return *(zbx_uint64_t*)lua_touserdata(L, index);
	else if (lua_isnumber(L, index))
		return (zbx_uint64_t) lua_tonumber(L, index);
	else
	{
		lua_pushstring(L, "Invalid operand. Expected '"U64_LUA_TYPE"' or 'number'");
		lua_error(L);

		return 0; /* will never get here */
	}
}

int	lua_pushuint64(lua_State *L, zbx_uint64_t val) {
	zbx_uint64_t	*ud = lua_newuserdata(L, sizeof(zbx_uint64_t));

	*ud = val;
	luaL_getmetatable(L, U64_LUA_TYPE);
	lua_setmetatable(L, -2);

	return 1;
}

int	lua_uint64_tostring(lua_State *L)
{
	char str[U64_STR_LEN];

	zbx_snprintf(str, U64_STR_LEN, ZBX_FS_UI64, lua_touint64(L, -1));
	lua_pushstring(L, str);
	return 1;
}

static int uint64_new (lua_State *L) { return lua_pushuint64(L, lua_touint64(L, 1) ); }
static int uint64_add (lua_State *L) { return lua_pushuint64(L, lua_touint64(L, 1) + lua_touint64(L, 2) ); }
static int uint64_sub (lua_State *L) { return lua_pushuint64(L, lua_touint64(L, 1) - lua_touint64(L, 2) ); }
static int uint64_mul (lua_State *L) { return lua_pushuint64(L, lua_touint64(L, 1) * lua_touint64(L, 2) ); }
static int uint64_div (lua_State *L) { return lua_pushuint64(L, lua_touint64(L, 1) / lua_touint64(L, 2) ); }
static int uint64_mod (lua_State *L) { return lua_pushuint64(L, lua_touint64(L, 1) % lua_touint64(L, 2) ); }
static int uint64_unm (lua_State *L) { return lua_pushuint64(L, -lua_touint64(L, 1) ); }
static int uint64_eq  (lua_State *L) { lua_pushboolean(L, lua_touint64(L, 1) == lua_touint64(L, 2) ); return 1; }
static int uint64_lt  (lua_State *L) { lua_pushboolean(L, lua_touint64(L, 1) <  lua_touint64(L, 2) ); return 1; }
static int uint64_le  (lua_State *L) { lua_pushboolean(L, lua_touint64(L, 1) <= lua_touint64(L, 2) ); return 1; }

static int	uint64_concat(lua_State *L)
{
	char *str1, *str2;
	char *result;

	if (lua_isuint64(L, 1))
		str1 = zbx_dsprintf(NULL, ZBX_FS_UI64, lua_touint64(L, 1));
	else
		str1 = zbx_dsprintf(NULL, "%s", lua_tostring(L, 1));

	if (lua_isuint64(L, 2))
		str2 = zbx_dsprintf(NULL, ZBX_FS_UI64, lua_touint64(L, 2));
	else
		str2 = zbx_dsprintf(NULL, "%s", lua_tostring(L, 2));

	result = zbx_dsprintf(NULL, "%s%s", str1, str2);
	lua_pushstring(L, result);

	zbx_free(result);
	zbx_free(str1);
	zbx_free(str2);

	return 1;
}

int	luaopen_uint64 (lua_State *L) {
	static const struct luaL_Reg uint64_type[] = {
		{ "__add", uint64_add },
		{ "__sub", uint64_sub },
		{ "__mul", uint64_mul },
		{ "__div", uint64_div },
		{ "__mod", uint64_mod },
		{ "__unm", uint64_unm },
		{ "__eq",  uint64_eq  },
		{ "__lt",  uint64_lt  },
		{ "__le",  uint64_le  },
		{ "__tostring", lua_uint64_tostring},
		{ "__concat", uint64_concat},
		{ NULL }
	};

	luaL_newmetatable(L, U64_LUA_TYPE);
	luaL_register(L, NULL, uint64_type);

	/* register function uint64() for creating uint64 variables */
	lua_register(L, U64_LUA_TYPE, uint64_new);
	return 0;
}
