#
# $Id: distro.sh 1 2008-03-14 17:38:38Z mhashmi $
#
#/bin/bash

declare -a RELEASE_ARRAY
declare -a MODEL_ARRAY
declare -a CPU_ARRAY
declare -a FILE_ARRAY

FILE_PATTERN="{[^lsb]*-release,[^lsb]*_version,[^lsb]*-version}"
FILE_ARRAY=( $(eval ls /etc/$FILE_PATTERN 2>/dev/null) )
if [ ${#FILE_ARRAY[1]} -gt 0 ] ; then
  FILE=${FILE_ARRAY[1]}
else
  FILE=${FILE_ARRAY[0]}
fi
PRE_DISTRO=${FILE##*/}
DISTRO=${PRE_DISTRO%%-*}
SYSTEM=$(uname -s)
MACH_TYPE=$(uname -m)
KERNEL=$(uname -r)
RAW_GLIBC_VER=$(echo $(getent -V)) 
PRE_GLIBC_VER=${RAW_GLIBC_VER%%C*}
GLIBC_VERSION=${PRE_GLIBC_VER#*) }
GLIBC="glibc-$GLIBC_VERSION"

if [ "$FILE" ] ; then
  let x=0
  while read RELEASEINFO ; do
    RELEASE_ARRAY[$x]=$RELEASEINFO
    let x++
  done < ${FILE##* }
fi 

while read CPUINFO ; do 
  case ${CPUINFO::7} in  
    "model n") 
      MODEL_ARRAY=($CPUINFO)
      VENDOR_CLASS=${MODEL_ARRAY[3]}
      VENDOR_MODEL=${MODEL_ARRAY[4]}
      VENDOR_NAME=${MODEL_ARRAY[5]}
      ;;
    "cpu MHz") 
      CPU_ARRAY=($CPUINFO)
      CPU_MHZ=${CPU_ARRAY[3]}
      ;;
  esac 
done < /proc/cpuinfo  

if [ ${RELEASE_ARRAY[2]} ] ; then
  PRE_RELEASE=${RELEASE_ARRAY[0]}-${RELEASE_ARRAY[2]}
else
  PRE_RELEASE=${RELEASE_ARRAY[0]}
fi  
RELEASE=${PRE_RELEASE// /_}
PARTITIONS=$(awk '/^ +[0-9]+ +[0-9]+ +[0-9]+ / {printf("%s=%s,",$4,$3)}  END{printf("\n")}' /proc/partitions)
echo $VENDOR_CLASS:$VENDOR_MODEL:$VENDOR_NAME:$CPU_MHZ:$SYSTEM:$MACH_TYPE:$DISTRO:$RELEASE:$KERNEL:$GLIBC:$PARTITIONS
exit 
