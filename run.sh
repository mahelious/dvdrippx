#!/bin/bash

sudo apt-get update
sudo apt-get install -y git curl gcc build-essential pkg-config autoconf make cmake automake tar unzip at

# setup output dir structure
# to be really cool, map a network directory to /usr/ripx/output
sudo mkdir -p /usr/ripx/mkv && sudo chmod 777 /usr/ripx/mkv
sudo mkdir -p /usr/ripx/processing && sudo chmod 777 /usr/ripx/processing
sudo mkdir -p /usr/ripx/output && sudo chmod 777 /usr/ripx/output

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
wget -O Downloads/ccextractor-0.87.zip https://github.com/CCExtractor/ccextractor/archive/v0.87.zip
unzip -q Downloads/ccextractor-0.87.zip -d ccextractor
cd ccextractor/ccextractor-0.87/linux && ./build
sudo chown root:root ccextractor
sudo mv ccextractor /usr/local/bin
cd /usr/bin
sudo ln -s /usr/local/bin/ccextractor mmccextr
cd
sed -i "s/app_ccextractor = /#app_ccextractor = /" ~/.MakeMKV/settings.conf
echo 'app_ccextractor = "/usr/bin/mmccextr"' >> ~/.MakeMKV/settings.conf

# install PHP 7.2
sudo apt-get install -y php7.2-cli

# configure udev to mount in the real space, instead of the default behavior of mounting in a private space like an asshole
sudo mkdir -p /etc/systemd/system/systemd-udevd.service.d
echo -e "[Service]\nMountFlags=shared" | sudo tee -a /etc/systemd/system/systemd-udevd.service.d/mount_flags.conf
# note this works in udev v237; starting in v239 instead run
#echo -e "[Service]\nPrivateMounts=no" | sudo tee -a /etc/systemd/system/systemd-udevd.service.d/private_mounts.conf

# setup udev to oblige handling input/output mounting
echo 'SUBSYSTEM=="block", ENV{ID_PATH}=="pci-0000:00:17.0-ata-3", ACTION=="change", RUN+="/usr/ripx/drive_change.sh"' | sudo tee -a /etc/udev/rules.d/autodvd.rules

# TODO install handbrake-cli
sudo apt-get install -y libass-dev libbz2-dev libfontconfig1-dev libfreetype6-dev libfribidi-dev libharfbuzz-dev libjansson-dev liblzma-dev libmp3lame-dev libogg-dev libopus-dev libsamplerate-dev libspeex-dev libtheora-dev libtool libtool-bin libvorbis-dev libx264-dev libxml2-dev m4 nasm patch python yasm zlib1g-dev
cd
git clone https://github.com/HandBrake/HandBrake.git
cd Handbrake
git checkout 1.2.2
./configure --launch-jobs=$(nproc) --disable-gtk --launch
sudo mv build/HandBrakeCLI /usr/bin
sudo chown root:root /usr/bin/HandBrakeCLI
sudo chmod 755 /usr/bin/HandBrakeCLI

# TODO add crontab job to run the encoder
