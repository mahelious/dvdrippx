#!/bin/bash

DVD_MOUNT=/mnt/dvd
USERNAME=`whoami`
LOGFILE=/var/log/dvdrippx/dvdrip.log

{
    if [ ! -d $DVD_MOUNT ]; then
        echo "Creating mount directory $DVD_MOUNT" >> $LOGFILE
        mkdir -p $DVD_MOUNT
        echo "Created mount directory" >> $LOGFILE
    fi

    if [ ! -z "${ID_CDROM_MEDIA}" ] && [ $ID_CDROM_MEDIA -eq 1 ]; then
        echo "active user: $USERNAME" >> $LOGFILE
        echo "$(date +"%Y-%m-%d %k:%M:%S") Disc insert detected" >> $LOGFILE
        echo "Mounting device $DEVNAME to $DVD_MOUNT"
        systemd-mount --no-block --automount=yes --collect $DEVNAME $DVD_MOUNT
        echo "Device $DEVNAME mounted at $DVD_MOUNT"
        echo "Launching extractor"
        echo "/usr/bin/php /opt/dvdrippx/scripts/extract.php >> $LOGFILE && eject" | at now
    fi
} &>> "$LOGFILE"
