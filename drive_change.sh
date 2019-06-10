#!/bin/bash

DVD_MOUNT=/media/dvd0

{
	if [ ! -z "${ID_CDROM_MEDIA}" ] && [ $ID_CDROM_MEDIA -eq 1 ]; then
		echo "$(date +"%Y-%m-%d %k:%M:%S") Disc insert detected"
		mkdir -p $DVD_MOUNT
		mount -t $ID_FS_TYPE -o ro,users $DEVNAME $DVD_MOUNT
		# nohup shit does not work in this context :(
		# nohup php dvdrippx/extract.php >> /var/log/dvdrippx/dvdrip.log &
		echo '/usr/bin/php /home/matthew/dvdrippx/extract.php >> /var/log/dvdrippx/dvdrip.log' | at now
	else
		if grep -q $DVD_MOUNT /proc/mounts ; then
			umount -l $DVD_MOUNT
		fi
		if [ -d $DVD_MOUNT ]; then
			rm -rf $DVD_MOUNT
		fi
	fi
} &>> "/var/log/dvdrippx/dvdrip.log"