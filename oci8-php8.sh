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

sudo sh -c "echo 'instantclient,/opt/oracle/instantclient' | pecl install oci8-3.0.1"

# setup ld library path
sudo sh -c "echo '/opt/oracle/instantclient' >> /etc/ld.so.conf"
sudo ldconfig
