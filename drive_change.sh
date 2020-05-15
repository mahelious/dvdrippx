#!/bin/bash

DVD_MOUNT=/mnt/dvd

{
	if [ ! -z "${ID_CDROM_MEDIA}" ] && [ $ID_CDROM_MEDIA -eq 1 ]; then
		echo "$(date +"%Y-%m-%d %k:%M:%S") Disc insert detected" >> /var/log/dvdrippx/dvdrip.log
		mkdir -p $DVD_MOUNT
		mount -t $ID_FS_TYPE -o ro,users $DEVNAME $DVD_MOUNT
		# note: nohup does not work in this context
         echo "/usr/bin/php /opt/dvdrippx/scripts/extract.php >> /var/log/dvdrippx/dvdrip.log && eject" | at now
	else
		if grep -q $DVD_MOUNT /proc/mounts ; then
			umount -l $DVD_MOUNT
		fi
		if [ -d $DVD_MOUNT ]; then
			rm -rf $DVD_MOUNT
		fi
	fi
} &>> "/var/log/dvdrippx/dvdrip.log"