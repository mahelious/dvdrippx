#!/bin/bash

# note: udev runs this script as root, uncomment following line to verify
#echo "active user: `whoami`"

DVD_MOUNT=/mnt/dvd
LOGFILE=/var/log/dvdrippx/dvdrip.log

#sudo udevadm info --root --name=/dev/sr0
echo "ID_FS_TYPE=$ID_FS_TYPE" >> $LOGFILE
echo "ID_FS_LABEL=$ID_FS_LABEL" >> $LOGFILE
echo "DEVTYPE=$DEVTYPE" >> $LOGFILE
echo "DEVNAME=$DEVNAME" >> $LOGFILE
echo "ID_CDROM_MEDIA=$ID_CDROM_MEDIA" >> $LOGFILE
echo "ID_CDROM_MEDIA_BD=$ID_CDROM_MEDIA_BD" >> $LOGFILE
echo "ID_CDROM_MEDIA_CD=$ID_CDROM_MEDIA_CD" >> $LOGFILE
echo "ID_CDROM_MEDIA_DVD=$ID_CDROM_MEDIA_DVD" >> $LOGFILE

{
    if [ ! -d $DVD_MOUNT ]; then
        echo "Creating mount directory $DVD_MOUNT" >> $LOGFILE
        mkdir -p $DVD_MOUNT
        echo "Created mount directory" >> $LOGFILE
    fi

    if [ ! -z "${ID_CDROM_MEDIA}" ] && [ $ID_CDROM_MEDIA -eq 1 ]; then
        echo "$(date +"%Y-%m-%d %k:%M:%S") Disc insert detected" >> $LOGFILE
        if mountpoint -q $DVD_MOUNT; then
            echo "$DVD_MOUNT already mounted, skipping"
        else
          #echo "Mounting device $DEVNAME to $DVD_MOUNT"
          systemd-mount --no-block --automount=yes --collect $DEVNAME $DVD_MOUNT
          echo "Device $DEVNAME mounted at $DVD_MOUNT"
          if [ $ID_CDROM_MEDIA_BD -eq 1 ] || [ $ID_CDROM_MEDIA_DVD -eq 1 ]; then
            echo "Launching video/audio extractor"
            echo "/usr/bin/php /opt/dvdrippx/scripts/extract.php >> $LOGFILE && eject" | at now
          elif [ $ID_CDROM_MEDIA_CD -eq 1 ]; then
            echo "Launching audio-only extractor"
            # TODO ...
          fi
        fi
    fi
} &>> "$LOGFILE"
