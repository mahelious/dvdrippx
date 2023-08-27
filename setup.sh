#!/bin/bash

# assumed environment:
# Linux Mint 21.1 (upstream Ubuntu 22.04 Jammy Jellyfish)

APP_DIR=$(dirname -- "$(readlink -f -- "$0";)")

sudo apt-get -qq update --fix-missing \
  && sudo DEBIAN_FRONTEND=noninteractive apt-get upgrade -y \
  && sudo apt-get install -y at abcde curl flac flatpak git mkvtoolnix php-cli unzip

# install pup to scrape the makemkv beta key
wget -O pup.zip https://github.com/ericchiang/pup/releases/download/v0.4.0/pup_v0.4.0_linux_amd64.zip \
  && unzip pup.zip \
  && sudo mv pup /usr/local/bin/ \
  && rm pup.zip

## make the ripper command executable
$sudo chmod +x $APP_DIR/rippx

# install makemkv from the heyarje/makemkv-beta 3rd-party repo
if [[ $(find /etc/apt/ -name *.list | xargs cat | grep "heyarje/makemkv-beta") -ne 0 ]]; then
  sudo add-apt-repository -y ppa:heyarje/makemkv-beta > /dev/null 2>&1
fi
sudo apt-get -qq update \
  && sudo apt-get install -y makemkv-bin makemkv-oss

# scrape the makemkv beta key
wget -O forum-key.html https://forum.makemkv.com/forum/viewtopic.php?t=1053 \
  && MAKEMKV_REG=$(cat forum-key.html | pup '#page-body .postbody .content code text{}') \
  && echo "app_Key = \"$MAKEMKV_REG\"" > .MakeMKV/settings.conf \
  && rm forum-key.html

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

# recommended to install Handbrake from Flatpak
sudo flatpak remote-add --if-not-exists flathub https://dl.flathub.org/repo/flathub.flatpakrepo \
  && sudo flatpak install -y flathub fr.handbrake.ghb

# configure udev to mount in the real space, instead of the default behavior of mounting in a private space like an asshole
UDEV_CONF_DIR="/etc/systemd/system/systemd-udevd.service.d"
if [ ! -d $UDEV_CONF_DIR ]; then
  sudo mkdir -p $UDEV_CONF_DIR
fi
echo -e "[Service]\nPrivateMounts=no" | sudo tee $UDEV_CONF_DIR/private_mounts.conf

# setup udev to automate handling input/output mounting
# 1. Get machine address of drive
ARG_IDPATH=udevadm info -q all -n /dev/cdrom | grep "ID_PATH="
IFS='='
read -ra ARR_IDPATH <<< "$ARG_IDPATH"
# 2. Create the udev rule
UDEV_RULE="SUBSYSTEM==\"block\", ENV{ID_PATH}==\"${ARR_IDPATH[1]}\", ACTION==\"change\", RUN+=\"$APP_DIR/drive_change.sh\""
echo $UDEV_RULE | sudo tee /etc/udev/rules.d/80-autodvd.rules

# add a cron entry to run the encoder every hour
CRON_ENCODER_JOB="*/30 * * * * php $APP_DIR/scripts/encode.php"
(crontab -l 2>/dev/null; echo $CRON_ENCODER_JOB) | crontab -

# setup logging
mkdir -p $APP_DIR/logs
touch $APP_DIR/logs/dvdrip.log
chmod 644 $APP_DIR/logs/dvdrip.log
LOGROTATE="$APP_DIR/logs/* {\n\tdaily\n\trotate 7\n\tnocompress\n\tcreate\n\tmissingok\n\tnotifempty\n}"
echo -e $LOGROTATE | sudo tee /etc/logrotate.d/dvdrippx
