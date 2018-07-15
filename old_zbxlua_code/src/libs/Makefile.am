## Process this file with automake to produce Makefile.in

DIST_SUBDIRS = \
	zbxdbcache \
	zbxdbhigh \
	zbxdb \
	zbxmemory \
	zbxalgo \
	zbxmedia \
	zbxcrypto \
	zbxcommon \
	zbxcomms \
	zbxcommshigh \
	zbxlog \
	zbxlua \
	zbxnix \
	zbxconf \
	zbxsysinfo \
	zbxsys \
	zbxjson \
	zbxserver \
	zbxicmpping \
	zbxexec \
	zbxself

if PROXY
PROXY_SUBDIRS = \
	zbxcommshigh \
	zbxdb \
	zbxdbcache \
	zbxdbhigh \
	zbxmemory \
	zbxserver \
	zbxicmpping \
	zbxself
endif

if SERVER
SERVER_SUBDIRS = \
	zbxcommshigh \
	zbxdb \
	zbxdbcache \
	zbxdbhigh \
	zbxmemory \
	zbxserver \
	zbxicmpping \
	zbxmedia \
	zbxself
endif

SUBDIRS = \
	zbxcrypto \
	zbxcommon \
	zbxlog \
	zbxlua \
	zbxalgo \
	zbxnix \
	zbxconf \
	zbxsysinfo \
	zbxsys \
	zbxcomms \
	zbxjson \
	zbxexec \
	$(PROXY_SUBDIRS) \
	$(SERVER_SUBDIRS)

EXTRA_DIST = zbxwin32
