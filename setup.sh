#!/bin/bash

# assumed environment:
# Linux Mint 21.1 (upstream Ubuntu 22.04 Jammy Jellyfish)

sudo apt-get -qq update --fix-missing \
  && sudo DEBIAN_FRONTEND=noninteractive apt-get upgrade -y \
  && sudo apt-get install -y at curl flatpak git php-cli unzip

sudo add-apt-repository -y ppa:heyarje/makemkv-beta > /dev/null 2>&1 \
  && sudo apt-get -qq update \
  && sudo apt-get install -y makemkv-bin makemkv-oss

# install pup to scrape the makemkv beta key
wget -O pup.zip https://github.com/ericchiang/pup/releases/download/v0.4.0/pup_v0.4.0_linux_amd64.zip \
  && unzip pup.zip \
  && sudo mv pup /usr/local/bin/ \
  && rm pup.zip

# scrape the makemkv beta key
wget -O forum-key.html https://forum.makemkv.com/forum/viewtopic.php?t=1053 \
  && MAKEMKV_REG=$(cat forum-key.html | pup '#page-body .postbody .content code text{}') \
  && echo "app_Key = \"$MAKEMKV_REG\"" > .MakeMKV/settings.conf \
  && rm forum-key.html

# setup logging
# points for style, but this can be done later
#sudo mkdir -p /var/log/dvdrippx
#sudo chmod 755 /var/log/dvdrippx
#sudo touch /var/log/dvdrippx/dvdrip.log
#sudo chmod 644 /var/log/dvdrippx/dvdrip.log
#echo -e "/var/log/dvdrippx/*log {\n\tdaily\n\trotate 7\n\tnocompress\n\tcreate\n\tmissingok\n\tnotifempty\n}" | sudo tee -a /etc/logrotate.d/dvdrippx

# install ccextractor as a supplement for makemkv
sudo apt-get install -y cargo clang tesseract-ocr libtesseract-dev libcurl4-gnutls-dev libleptonica-dev \
  && wget -O ccextractor.zip https://github.com/CCExtractor/ccextractor/archive/v0.94.zip \
  && unzip -q ccextractor.zip -d ccextractor \
  && cd ccextractor/ccextractor-0.94/linux \
  && ./build \
  && sudo chown root:root ccextractor \
  && sudo mv ccextractor /usr/local/bin \
  && cd \
  && sed -i "s/app_ccextractor = /#app_ccextractor = /" ~/.MakeMKV/settings.conf \
  && echo 'app_ccextractor = "/usr/bin/mmccextr"' >> ~/.MakeMKV/settings.conf

# Recommended to install Handbrake from Flatpak
sudo flatpak remote-add --if-not-exists flathub https://dl.flathub.org/repo/flathub.flatpakrepo \
  && sudo flatpak install -y flathub fr.handbrake.ghb

## configure udev to mount in the real space, instead of the default behavior of mounting in a private space like an asshole
#sudo mkdir -p /etc/systemd/system/systemd-udevd.service.d
#echo -e "[Service]\nMountFlags=shared" | sudo tee -a /etc/systemd/system/systemd-udevd.service.d/mount_flags.conf
## note this works in udev v237; starting in v239 instead run
##echo -e "[Service]\nPrivateMounts=no" | sudo tee -a /etc/systemd/system/systemd-udevd.service.d/private_mounts.conf
#
## setup udev to oblige handling input/output mounting
#echo 'SUBSYSTEM=="block", ENV{ID_PATH}=="pci-0000:00:17.0-ata-3", ACTION=="change", RUN+="/opt/dvdrippx/drive_change.sh"' | sudo tee -a /etc/udev/rules.d/autodvd.rules
#
##$ sudo crontab -l
##*/30 * * * * php /opt/dvdrippx/scripts/encode.php >> /opt/dvdrippx/logs/dvdrip.log 2>&1
#
##$ sudo cat /etc/udev/rules.d/autodvd.rules
##SUBSYSTEM=="block", ENV{ID_PATH}=="pci-0000:00:17.0-ata-3", ACTION=="change", RUN+="/opt/dvdrippx/drive_change.sh"
#
## make the ripper command executable
#$sudo chmod +x /opt/dvdrippx/rippx
