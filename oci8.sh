#!/usr/bin/env bash

# install deps
sudo apt-get update -qq
sudo apt-get -y install -qq build-essential unzip wget libaio1

# install oci8 libs & extension
sudo mkdir -p /opt/oracle

wget https://download.oracle.com/otn_software/linux/instantclient/199000/instantclient-basic-linux.x64-19.9.0.0.0dbru.zip
wget https://download.oracle.com/otn_software/linux/instantclient/199000/instantclient-sdk-linux.x64-19.9.0.0.0dbru.zip

sudo unzip -o ./instantclient-basic-linux.x64-19.9.0.0.0dbru.zip -d /opt/oracle
sudo unzip -o ./instantclient-sdk-linux.x64-19.9.0.0.0dbru.zip -d /opt/oracle

sudo ln -s /opt/oracle/instantclient/sqlplus /usr/bin/sqlplus
sudo ln -s /opt/oracle/instantclient_19_9 /opt/oracle/instantclient

PHPVersion=$(php --version | tail -r | tail -n 1 | cut -d " " -f 2 | cut -c 1,3)
if [ $(echo " $PHPVersion <= 80" | bc) -eq 1 ]; then
    sudo sh -c "echo 'instantclient,/opt/oracle/instantclient' | pecl install oci8-2.2.0"
else
    sudo sh -c "echo 'instantclient,/opt/oracle/instantclient' | pecl install oci8"
fi

# setup ld library path
sudo sh -c "echo '/opt/oracle/instantclient' >> /etc/ld.so.conf"
sudo ldconfig
