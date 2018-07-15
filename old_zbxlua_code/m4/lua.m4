AC_DEFUN([LUA_CHECK_CONFIG],
[
  AC_ARG_WITH(lua,[If you want to check for Lua support:
AC_HELP_STRING([--with-lua@<:@=DIR@:>@],[Include Lua support @<:@default=no@:>@.  DIR is the Lua base install directry, default is to search common locations for Lua files.])
],[ if test "$withval" = "no"; then
	want_lua="no"
	_liblua_with="no"
  elif test "$withval" = "yes"; then
	want_lua="yes"
	_liblua_with="yes"
  else
	want_lua="yes"
	_liblua_with=$withval
  fi
],[_liblua_with=ifelse([$1],,[no],[$1])])

if test "x$_liblua_with" != "xno"; then
	if test "$_liblua_with" = "yes"; then
		PKG_CHECK_MODULES([LLUA],[lua], [LUA_INCDIR=$LLUA_CFLAGS LUA_LIBDIR=$LLUA_LIBS LUA_LIBS="-llua"],
			[PKG_CHECK_MODULES([LLUA],[lua5.1], [LUA_INCDIR=$LLUA_CFLAGS LUA_LIBDIR=$LLUA_LIBS LUA_LIBS="-llua5.1"],
				[PKG_CHECK_MODULES([LLUA],[lua-5.1], [LUA_INCDIR=$LLUA_CFLAGS LUA_LIBDIR=$LLUA_LIBS LUA_LIBS="-llua-5.1"],
					[PKG_CHECK_MODULES([LLUA],[lua51], [LUA_INCDIR=$LLUA_CFLAGS LUA_LIBDIR=$LLUA_LIBS LUA_LIBS="-llua51"],
						[found_lua="no"])])])])
	else
		AC_MSG_CHECKING(for Lua support)
		if test -f $_liblua_with/include/lua.h; then
			LUA_INCDIR=-I$_liblua_with/include
			LUA_LIBDIR=-L$_liblua_with/lib
			LUA_LIBS="-llua"
			AC_MSG_RESULT(yes)
		else
			found_lua="no"
			AC_MSG_RESULT(no)
		fi
	fi

	if test "x$found_lua" != "xno"; then

		LUA_CPPFLAGS="$LUA_INCDIR"
		LUA_LDFLAGS="$LUA_LIBDIR"

		found_lua="yes"
		AC_DEFINE(HAVE_LUA,1,[Define to 1 if Lua library should be enabled.])
	fi
fi

AC_SUBST(LUA_CPPFLAGS)
AC_SUBST(LUA_LDFLAGS)
AC_SUBST(LUA_LIBS)

unset _liblua_with
])dnl

