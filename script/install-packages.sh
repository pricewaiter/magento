#!/usr/bin/env bash
set -ex

sudo apt-get -qq update
sudo apt-get -qq install libxml2 libxml2-utils nginx-full
