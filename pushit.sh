#!/bin/bash



if [ -z "$1" ]; then
  echo "No destination host passed in"
  exit 1
fi

#rsync -av --include=*.ac --include=*.am --include=*.h --include=*.c --exclude=*.* . $1:zabbix-lua
rsync -av --exclude=*.in --exclude=.idea --exclude=configure --exclude=aclocal.m4 --exclude=stamp-h1 . $1:zabbix-lua