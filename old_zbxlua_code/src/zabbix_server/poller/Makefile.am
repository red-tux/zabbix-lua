## Process this file with automake to produce Makefile.in

noinst_LIBRARIES = libzbxpoller.a

if LUA
LUA_SOURCES = checks_lua.c checks_lua.h 
endif

libzbxpoller_a_SOURCES = \
	checks_agent.c checks_agent.h \
	checks_internal.c checks_internal.h \
	checks_simple.c checks_simple.h \
	checks_snmp.c checks_snmp.h \
	checks_db.c checks_db.h \
	checks_aggregate.c checks_aggregate.h \
	checks_external.c checks_external.h \
	checks_ipmi.c checks_ipmi.h \
	checks_ssh.c checks_ssh.h \
	checks_telnet.c checks_telnet.h \
	checks_java.c checks_java.h \
	checks_calculated.c checks_calculated.h \
	poller.c poller.h \
	$(LUA_SOURCES)

libzbxpoller_a_CFLAGS = -I@top_srcdir@/src/libs/zbxsysinfo/simple
