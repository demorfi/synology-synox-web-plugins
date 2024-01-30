#!/bin/sh

echo $'##################################################################
# Synology SynoX Web Make.                                       #
#                                                                #
# @author  demorfi <demorfi@gmail.com>                           #
# @version 1.2                                                   #
# @uses    https://github.com/demorfi/synox-web                  #
# @source  https://github.com/demorfi/synology-synox-web-plugins #
# @license http://www.opensource.org/licenses/mit-license.php    #
##################################################################
';

. ./functions

BUILD_DIR=builds
VERSION=1.2
PACKAGES="au-synox-web bt-synox-web ht-synox-web"
EXTENSIONS="aum dlm host"
PATH_DIR=`pwd`
ENV_FILE=".env"
TEMP_FILES="$ENV_FILE synox-web-$VERSION.zip"

build()
{
  echo "build..."
  if [ ! -d $BUILD_DIR ]; then
    mkdir $BUILD_DIR
  fi

  load_env
  for i in $PACKAGES
    do
      cd $PATH_DIR/src/$i
      ./make build $ENV_URL $ENV_DEBUG
      for j in $EXTENSIONS
        do
          if [ $j ] && [ -f "$PATH_DIR/src/$i/synox-web.$j" ]; then
            cp $PATH_DIR/src/$i/synox-web.$j $PATH_DIR/$BUILD_DIR
          fi
        done
      cd $PATH_DIR
    done

  cp LICENSE $BUILD_DIR
  cp README.md $BUILD_DIR
  cp CHANGELOG.md $BUILD_DIR

  echo "create archive..."
  (cd $BUILD_DIR ; zip -r synox-web-$VERSION.zip ./)
  echo "...done"
}

clean()
{
  echo "clean..."
  if [ -d $BUILD_DIR ]; then
    echo "delete $BUILD_DIR"
    rm -rf $BUILD_DIR
  fi

  for i in $TEMP_FILES
    do
      if [ -f $i ]; then
        echo "delete $i"
        rm -f $i
      fi
    done

  for i in $PACKAGES
    do
      cd $PATH_DIR/src/$i
      ./make clean
    done
  echo "...done"
}

if [ ! $1 ] || [ $1 = "build" ]; then
  build
  exit 0
elif [ $1 ]; then
  if [ $1 = "clean" ]; then
    clean
    exit 0
  fi
fi

exit 0