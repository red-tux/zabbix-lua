#include "log.h"
#include "common.h"
#include "dbcache.h"

#include "lua.h"
#include "zbxlua.h"
#include "checks_lua.h"

/******************************************************************************
 *                                                                            *
 * Function: get_lua_item                                                     *
 *                                                                            *
 * Purpose: Wrapper to the execute_string function in zbxlua.                 *
 *                                                                            *
 * Parameters: Lua State, Pointer to information about the item               *
 *             Pointer to the return (result)                                 *
 *                                                                            *
 * Return value: Success/Fail                                                 *
 *                                                                            *
 * Author: Andrew Nelson                                                      *
 *                                                                            *
 * Comments:                                                                  *
 *                                                                            *
 ******************************************************************************/

int get_lua_item(lua_State *L, DC_ITEM *item, AGENT_RESULT *result)
{
	const char	*_name = "get_lua_item";
	double		sec;
	int		ret = SUCCEED;
	const char	*str = NULL;
	char		params[MAX_STRING_LEN], cmd[MAX_STRING_LEN];

	if (!item->params_orig)
	{
		SET_MSG_RESULT(result, strdup("no script found"));
		zabbix_log(LOG_LEVEL_DEBUG, "[%s] no script found", _name);

		return NOTSUPPORTED;
	}

	sec = zbx_time();

	parse_command(item->key, cmd, sizeof(cmd), params, sizeof(params));
	
	zabbix_log(LOG_LEVEL_DEBUG, "[%s] Executing Lua script itemid:%d  script:%s",
			_name, item->itemid, item->params_orig);

	ret = execute_lua(L, params, item);
	
	zabbix_log(LOG_LEVEL_DEBUG, "[%s] execute_lua return: %d", _name, ret);

	if (ret == SUCCEED)
	{
		/* execure_lua ensures the return value will corespond to the */
		switch (item->value_type)
		{
			case ITEM_VALUE_TYPE_FLOAT:
				SET_DBL_RESULT(result, lua_tonumber(L, -1));
				break;
			case ITEM_VALUE_TYPE_UINT64:
				SET_UI64_RESULT(result, lua_touint64(L, -1));
				break;
			case ITEM_VALUE_TYPE_STR:
			case ITEM_VALUE_TYPE_LOG:
				SET_STR_RESULT(result, strdup(lua_tostring(L, -1)));
				break;
			case ITEM_VALUE_TYPE_TEXT:
				SET_TEXT_RESULT(result, strdup(lua_tostring(L, -1)));
				break;
		}
	}
	else
	{
		str = lua_tostring(L, -1);

		SET_MSG_RESULT(result, strdup(str)); /* kick back the error message */
		zabbix_log(LOG_LEVEL_WARNING, "[%s] Lua execute error: %s", _name, str);

		ret = NOTSUPPORTED;
	}

	sec = zbx_time() - sec;

	zabbix_log(LOG_LEVEL_WARNING, "[%s] "ZBX_FS_DBL" seconds to execute Lua script itemid:%d  script:%s",
			_name, sec, item->itemid, item->params_orig);

	return ret;
}

