#!/bin/bash

# assumed environment:
# Linux Mint 21.1 (upstream Ubuntu 22.04 Jammy Jellyfish)

sudo apt-get update
sudo apt-get install -y git curl gcc build-essential pkg-config autoconf make cmake automake tar unzip at

# setup output dir structure
# to be really cool, map a network directory to /opt/dvdrippx/output
sudo mkdir -p /opt/dvdrippx/mkv && sudo chmod 777 /opt/dvdrippx/mkv
sudo mkdir -p /opt/dvdrippx/processing && sudo chmod 777 /opt/dvdrippx/processing
sudo mkdir -p /opt/dvdrippx/output && sudo chmod 777 /opt/dvdrippx/output

# setup logging
sudo mkdir -p /var/log/dvdrippx
sudo chmod 755 /var/log/dvdrippx
sudo touch /var/log/dvdrippx/dvdrip.log
sudo chmod 644 /var/log/dvdrippx/dvdrip.log
echo -e "/var/log/dvdrippx/*log {\n\tdaily\n\trotate 7\n\tnocompress\n\tcreate\n\tmissingok\n\tnotifempty\n}" | sudo tee -a /etc/logrotate.d/dvdrippx

#TODO make mkv user ??

#TODO install makemkv
# note: the key/secret must be copied into ~/.MakeMKV/settings.conf of whoever will run the script

# install ccextractor as a supplement for makemkv
sudo apt-get install -y tesseract-ocr libtesseract-dev libcurl4-gnutls-dev libleptonica-dev
wget -O Downloads/ccextractor.zip https://github.com/CCExtractor/ccextractor/archive/v0.94.zip
unzip -q Downloads/ccextractor.zip -d ccextractor
cd ccextractor/ccextractor/linux && ./build
sudo chown root:root ccextractor
sudo mv ccextractor /usr/local/bin
cd /usr/bin
sudo ln -s /usr/local/bin/ccextractor mmccextr
cd
sed -i "s/app_ccextractor = /#app_ccextractor = /" ~/.MakeMKV/settings.conf
echo 'app_ccextractor = "/usr/bin/mmccextr"' >> ~/.MakeMKV/settings.conf

# install PHP 8.1
sudo apt-get install -y php-cli

# configure udev to mount in the real space, instead of the default behavior of mounting in a private space like an asshole
sudo mkdir -p /etc/systemd/system/systemd-udevd.service.d
echo -e "[Service]\nMountFlags=shared" | sudo tee -a /etc/systemd/system/systemd-udevd.service.d/mount_flags.conf
# note this works in udev v237; starting in v239 instead run
#echo -e "[Service]\nPrivateMounts=no" | sudo tee -a /etc/systemd/system/systemd-udevd.service.d/private_mounts.conf

# setup udev to oblige handling input/output mounting
echo 'SUBSYSTEM=="block", ENV{ID_PATH}=="pci-0000:00:17.0-ata-3", ACTION=="change", RUN+="/opt/dvdrippx/drive_change.sh"' | sudo tee -a /etc/udev/rules.d/autodvd.rules

# TODO install handbrake-cli
#sudo apt-get install -y libass-dev libbz2-dev libfontconfig1-dev libfreetype6-dev libfribidi-dev libharfbuzz-dev libjansson-dev liblzma-dev libmp3lame-dev libogg-dev libopus-dev libsamplerate-dev libspeex-dev libtheora-dev libtool libtool-bin libvorbis-dev libx264-dev libxml2-dev m4 nasm patch python yasm zlib1g-dev
#sudo apt-get install libass-dev libbz2-dev libfontconfig1-dev libfreetype6-dev libfribidi-dev libharfbuzz-dev libjansson-dev liblzma-dev libmp3lame-dev libogg-dev libopus-dev libsamplerate0-dev libspeex-dev libtheora-dev libtool libtool-bin libvorbis-dev libx264-dev libxml2-dev m4 nasm patch yasm zlib1g-dev
#cd
#git clone https://github.com/HandBrake/HandBrake.git
#cd Handbrake
#git checkout 1.2.2
#./configure --launch-jobs=$(nproc) --disable-gtk --launch
#sudo mv build/HandBrakeCLI /usr/bin
#sudo chown root:root /usr/bin/HandBrakeCLI
#sudo chmod 755 /usr/bin/HandBrakeCLI

# Recommended to install Handbrake from Flatpak
# this could have repercussions for assumed dependencies from line 7 (apt-get install)
# need to re-evaluate this entire setup, rather than half-assed milestone notes

#$ sudo crontab -l
#*/30 * * * * php /opt/dvdrippx/scripts/encode.php >> /opt/dvdrippx/logs/dvdrip.log 2>&1

#$ sudo cat /etc/udev/rules.d/autodvd.rules 
#SUBSYSTEM=="block", ENV{ID_PATH}=="pci-0000:00:17.0-ata-3", ACTION=="change", RUN+="/opt/dvdrippx/drive_change.sh"

#$ sudo cat /etc/crontab
#//192.168.1.11/movies	/media/freenas/movies	cifs	auto,noexec,credentials=/root/.cifs_alepides_matthew,uid=1000	0	0
#//192.168.1.11/music-matthew	/media/freenas/music/matthew	cifs	auto,noexec,credentials=/root/.cifs_alepides_matthew,uid=1000	0	0
#//192.168.1.11/music-tamara	/media/freenas/music/tamara	cifs    auto,noexec,credentials=/root/.cifs_alepides_matthew,uid=1000   0       0
#//192.168.1.11/music-harrison	/media/freenas/music/harrison	cifs	auto,noexec,credentials=/root/.cifs_alepides_matthew,uid=1000	0	0


# make the ripper command executable
sudo chmod +x /opt/dvdrippx/rippx
