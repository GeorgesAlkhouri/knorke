#!/bin/bash

typeset -A ttlFiles

# knorke files
ttlFiles[./knowledge/knorke/knorke.ttl]=./knowledge/knorke/knorke.nt
ttlFiles[./knowledge/knorke/person.ttl]=./knowledge/knorke/person.nt
ttlFiles[./knowledge/knorke/shop.ttl]=./knowledge/knorke/shop.nt

for i in "${!ttlFiles[@]}"
do
    rm ${ttlFiles[$i]}
    rapper -i turtle -o ntriples $i > ${ttlFiles[$i]}
done
