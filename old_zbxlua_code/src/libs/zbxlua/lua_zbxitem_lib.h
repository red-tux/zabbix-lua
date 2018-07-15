
#ifndef ZBX_LUA_ITEM_H
#define ZBX_LUA_ITEM_H

int luaopen_zbxitem(lua_State *L);
int lua_zbxitem_find(lua_State *L, int narg, const DB_ITEM *item);

#endif
