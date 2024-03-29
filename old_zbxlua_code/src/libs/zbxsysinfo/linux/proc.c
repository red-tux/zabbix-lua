/*
** Zabbix
** Copyright (C) 2000-2011 Zabbix SIA
**
** This program is free software; you can redistribute it and/or modify
** it under the terms of the GNU General Public License as published by
** the Free Software Foundation; either version 2 of the License, or
** (at your option) any later version.
**
** This program is distributed in the hope that it will be useful,
** but WITHOUT ANY WARRANTY; without even the implied warranty of
** MERCHANTABILITY or FITNESS FOR A PARTICULAR PURPOSE.  See the
** GNU General Public License for more details.
**
** You should have received a copy of the GNU General Public License
** along with this program; if not, write to the Free Software
** Foundation, Inc., 51 Franklin Street, Fifth Floor, Boston, MA  02110-1301, USA.
**/

#include "common.h"
#include "sysinfo.h"

#define DO_SUM 0
#define DO_MAX 1
#define DO_MIN 2
#define DO_AVG 3

static FILE	*open_proc_file(const char *filename)
{
	struct stat	s;

	if (0 != stat(filename, &s))
		return NULL;

	return fopen(filename, "r");
}

static int	get_cmdline(FILE *f_cmd, char *line, size_t *n)
{
	rewind(f_cmd);

	if (0 != (*n = fread(line, 1, MAX_STRING_LEN, f_cmd)))
		return SUCCEED;

	return FAIL;
}

static int	get_procname(FILE *f_stat, char *line)
{
	char	tmp[MAX_STRING_LEN];

	rewind(f_stat);

	while (NULL != fgets(tmp, sizeof(tmp), f_stat))
	{
		if (0 != strncmp(tmp, "Name:\t", 6))
			continue;

		zbx_rtrim(tmp, "\n");
		zbx_strlcpy(line, tmp + 6, MAX_STRING_LEN);

		return SUCCEED;
	}

	return FAIL;
}

static int	check_procname(FILE *f_cmd, FILE *f_stat, const char *procname)
{
	char	tmp[MAX_STRING_LEN], *p;
	size_t	l;

	if (*procname == '\0')
		return SUCCEED;

	if (SUCCEED == get_procname(f_stat, tmp) && 0 == strcmp(tmp, procname))
		return SUCCEED;

	if (SUCCEED == get_cmdline(f_cmd, tmp, &l))
	{
		if (NULL == (p = strrchr(tmp, '/')))
			p = tmp;
		else
			p++;

		if (0 == strcmp(p, procname))
			return SUCCEED;
	}

	return FAIL;
}

static int	check_user(FILE *f_stat, struct passwd *usrinfo)
{
	char	tmp[MAX_STRING_LEN], *p, *p1;
	uid_t	uid;

	if (NULL == usrinfo)
		return SUCCEED;

	rewind(f_stat);

	while (NULL != fgets(tmp, sizeof(tmp), f_stat))
	{
		if (0 != strncmp(tmp, "Uid:\t", 5))
			continue;

		p = tmp + 5;

		if (NULL != (p1 = strchr(p, '\t')))
			*p1 = '\0';

		uid = (uid_t)atoi(p);

		if (usrinfo->pw_uid == uid)
			return SUCCEED;
		break;
	}

	return FAIL;
}

static int	check_proccomm(FILE *f_cmd, const char *proccomm)
{
	char	tmp[MAX_STRING_LEN];
	size_t	i, l;

	if (*proccomm == '\0')
		return SUCCEED;

	if (SUCCEED == get_cmdline(f_cmd, tmp, &l))
	{
		for (i = 0; i < l - 1; i++)
			if (tmp[i] == '\0')
				tmp[i] = ' ';

		if (NULL != zbx_regexp_match(tmp, proccomm, NULL))
			return SUCCEED;
	}

	return FAIL;
}

static int	check_procstate(FILE *f_stat, int zbx_proc_stat)
{
	char	tmp[MAX_STRING_LEN], *p;

	if (zbx_proc_stat == ZBX_PROC_STAT_ALL)
		return SUCCEED;

	rewind(f_stat);

	while (NULL != fgets(tmp, sizeof(tmp), f_stat))
	{
		if (0 != strncmp(tmp, "State:\t", 7))
			continue;

		p = tmp + 7;

		switch (zbx_proc_stat)
		{
			case ZBX_PROC_STAT_RUN:
				return (*p == 'R') ? SUCCEED : FAIL;
			case ZBX_PROC_STAT_SLEEP:
				return (*p == 'S') ? SUCCEED : FAIL;
			case ZBX_PROC_STAT_ZOMB:
				return (*p == 'Z') ? SUCCEED : FAIL;
			default:
				return FAIL;
		}
	}

	return FAIL;
}

int	PROC_MEM(const char *cmd, const char *param, unsigned flags, AGENT_RESULT *result)
{
	char		tmp[MAX_STRING_LEN], *p, *p1,
			procname[MAX_STRING_LEN],
			proccomm[MAX_STRING_LEN];
	DIR		*dir;
	struct dirent	*entries;
	struct passwd	*usrinfo;
	FILE		*f_cmd = NULL, *f_stat = NULL;
	zbx_uint64_t	value = 0;
	int		do_task;
	double		memsize = 0;
	int		proccount = 0;

	if (num_param(param) > 4)
		return SYSINFO_RET_FAIL;

	if (0 != get_param(param, 1, procname, sizeof(procname)))
		*procname = '\0';

	if (0 != get_param(param, 2, tmp, sizeof(tmp)))
		*tmp = '\0';

	if (*tmp != '\0')
	{
		usrinfo = getpwnam(tmp);
		if (usrinfo == NULL)	/* incorrect user name */
			return SYSINFO_RET_FAIL;
	}
	else
		usrinfo = NULL;

	if (0 != get_param(param, 3, tmp, sizeof(tmp)))
		*tmp = '\0';

	if (*tmp != '\0')
	{
		if (0 == strcmp(tmp, "avg"))
			do_task = DO_AVG;
		else if (0 == strcmp(tmp, "max"))
			do_task = DO_MAX;
		else if (0 == strcmp(tmp, "min"))
			do_task = DO_MIN;
		else if (0 == strcmp(tmp, "sum"))
			do_task = DO_SUM;
		else
			return SYSINFO_RET_FAIL;
	}
	else
		do_task = DO_SUM;

	if (0 != get_param(param, 4, proccomm, sizeof(proccomm)))
		*proccomm = '\0';

	if (NULL == (dir = opendir("/proc")))
		return SYSINFO_RET_FAIL;

	while (NULL != (entries = readdir(dir)))
	{
		zbx_fclose(f_cmd);
		zbx_fclose(f_stat);

		/* Self is a symbolic link. It leads to incorrect results for proc_cnt[zabbix_agentd] */
		/* Better approach: check if /proc/x/ is symbolic link */
		if (0 == strncmp(entries->d_name, "self", MAX_STRING_LEN))
			continue;

		zbx_snprintf(tmp, sizeof(tmp), "/proc/%s/cmdline", entries->d_name);

		if (NULL == (f_cmd = open_proc_file(tmp)))
			continue;

		zbx_snprintf(tmp, sizeof(tmp), "/proc/%s/status", entries->d_name);

		if (NULL == (f_stat = open_proc_file(tmp)))
			continue;

		if (FAIL == check_procname(f_cmd, f_stat, procname))
			continue;

		if (FAIL == check_user(f_stat, usrinfo))
			continue;

		if (FAIL == check_proccomm(f_cmd, proccomm))
			continue;

		rewind(f_stat);

		while (NULL != fgets(tmp, sizeof(tmp), f_stat))
		{
			if (0 != strncmp(tmp, "VmSize:\t", 8))
				continue;

			p = tmp + 8;

			if (NULL == (p1 = strrchr(p, ' ')))
				continue;

			*p1++ = '\0';

			ZBX_STR2UINT64(value, p);

			zbx_rtrim(p1, "\n");

			if (0 == strcasecmp(p1, "kB"))
				value <<= 10;
			else if(0 == strcasecmp(p1, "mB"))
				value <<= 20;
			else if(0 == strcasecmp(p1, "GB"))
				value <<= 30;
			else if(0 == strcasecmp(p1, "TB"))
				value <<= 40;

			if (0 == proccount++)
				memsize = value;
			else
			{
				if (do_task == DO_MAX)
					memsize = MAX(memsize, value);
				else if (do_task == DO_MIN)
					memsize = MIN(memsize, value);
				else
					memsize += value;
			}
			break;
		}
	}
	zbx_fclose(f_cmd);
	zbx_fclose(f_stat);
	closedir(dir);

	if (do_task == DO_AVG)
	{
		SET_DBL_RESULT(result, proccount == 0 ? 0 : memsize / proccount);
	}
	else
		SET_UI64_RESULT(result, memsize);

	return SYSINFO_RET_OK;
}

int	PROC_NUM(const char *cmd, const char *param, unsigned flags, AGENT_RESULT *result)
{
	char		tmp[MAX_STRING_LEN],
			procname[MAX_STRING_LEN],
			proccomm[MAX_STRING_LEN];
	DIR		*dir;
	struct dirent	*entries;
	struct passwd	*usrinfo;
	FILE		*f_cmd = NULL, *f_stat = NULL;
	int		zbx_proc_stat;
	zbx_uint64_t	proccount = 0;

	if (num_param(param) > 4)
		return SYSINFO_RET_FAIL;

	if (0 != get_param(param, 1, procname, sizeof(procname)))
		*procname = '\0';

	if (0 != get_param(param, 2, tmp, sizeof(tmp)))
		*tmp = '\0';

	if (*tmp != '\0')
	{
		usrinfo = getpwnam(tmp);
		if (usrinfo == NULL)	/* incorrect user name */
			return SYSINFO_RET_FAIL;
	}
	else
		usrinfo = NULL;

	if (0 != get_param(param, 3, tmp, sizeof(tmp)))
		*tmp = '\0';

	if (*tmp != '\0')
	{
		if (0 == strcmp(tmp, "run"))
			zbx_proc_stat = ZBX_PROC_STAT_RUN;
		else if (0 == strcmp(tmp, "sleep"))
			zbx_proc_stat = ZBX_PROC_STAT_SLEEP;
		else if (0 == strcmp(tmp, "zomb"))
			zbx_proc_stat = ZBX_PROC_STAT_ZOMB;
		else if (0 == strcmp(tmp, "all"))
			zbx_proc_stat = ZBX_PROC_STAT_ALL;
		else
			return SYSINFO_RET_FAIL;
	}
	else
		zbx_proc_stat = ZBX_PROC_STAT_ALL;

	if (0 != get_param(param, 4, proccomm, sizeof(proccomm)))
		*proccomm = '\0';

	if (NULL == (dir = opendir("/proc")))
		return SYSINFO_RET_FAIL;

	while (NULL != (entries = readdir(dir)))
	{
		zbx_fclose(f_cmd);
		zbx_fclose(f_stat);

		/* Self is a symbolic link. It leads to incorrect results for proc_cnt[zabbix_agentd] */
		/* Better approach: check if /proc/x/ is symbolic link */
		if (0 == strncmp(entries->d_name, "self", MAX_STRING_LEN))
			continue;

		zbx_snprintf(tmp, sizeof(tmp), "/proc/%s/cmdline", entries->d_name);

		if (NULL == (f_cmd = open_proc_file(tmp)))
			continue;

		zbx_snprintf(tmp, sizeof(tmp), "/proc/%s/status", entries->d_name);

		if (NULL == (f_stat = open_proc_file(tmp)))
			continue;

		if (FAIL == check_procname(f_cmd, f_stat, procname))
			continue;

		if (FAIL == check_user(f_stat, usrinfo))
			continue;

		if (FAIL == check_proccomm(f_cmd, proccomm))
			continue;

		if (FAIL == check_procstate(f_stat, zbx_proc_stat))
			continue;

		proccount++;
	}
	zbx_fclose(f_cmd);
	zbx_fclose(f_stat);
	closedir(dir);

	SET_UI64_RESULT(result, proccount);

	return SYSINFO_RET_OK;
}
