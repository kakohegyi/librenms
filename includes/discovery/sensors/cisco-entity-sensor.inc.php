<?php

use Illuminate\Support\Str;

if ($device['os_group'] == 'cisco') {
    echo ' CISCO-ENTITY-SENSOR: ';

    $oids = [];
    echo 'Caching OIDs:';

    if (empty($entity_array)) {
        $tmp_oids = ['entPhysicalDescr', 'entPhysicalName', 'entPhysicalClass', 'entPhysicalContainedIn', 'entPhysicalParentRelPos'];
        $entity_array = [];
        foreach ($tmp_oids as $tmp_oid) {
            echo " $tmp_oid";
            $entity_array = snmpwalk_cache_multi_oid($device, $tmp_oid, $entity_array, 'ENTITY-MIB:CISCO-ENTITY-SENSOR-MIB');
        }
        echo ' entAliasMappingIdentifier';
        $entity_array = snmpwalk_cache_twopart_oid($device, 'entAliasMappingIdentifier', $entity_array, 'ENTITY-MIB:IF-MIB');
    }

    $port_array = [];
    $port_array = snmpwalk_cache_multi_oid($device, 'ifName', $port_array, 'ENTITY-MIB:IF-MIB');
    $port_reverse_array = [];
    foreach ($port_array as $index => $port) {
        $port['ifIndex'] = $index;
        $port_reverse_array[$port['ifName']] = $port;
    }
    d_echo($port_reverse_array);

    echo '  entSensorType';
    $oids = snmpwalk_cache_multi_oid($device, 'entSensorType', $oids, 'CISCO-ENTITY-SENSOR-MIB');
    echo ' entSensorScale';
    $oids = snmpwalk_cache_multi_oid($device, 'entSensorScale', $oids, 'CISCO-ENTITY-SENSOR-MIB');
    echo ' entSensorValue';
    $oids = snmpwalk_cache_multi_oid($device, 'entSensorValue', $oids, 'CISCO-ENTITY-SENSOR-MIB');
    echo ' entSensorMeasuredEntity';
    $oids = snmpwalk_cache_multi_oid($device, 'entSensorMeasuredEntity', $oids, 'CISCO-ENTITY-SENSOR-MIB');
    echo ' entSensorPrecision';
    $oids = snmpwalk_cache_multi_oid($device, 'entSensorPrecision', $oids, 'CISCO-ENTITY-SENSOR-MIB');

    $t_oids = [];
    echo ' entSensorThresholdSeverity';
    $t_oids = snmpwalk_cache_twopart_oid($device, 'entSensorThresholdSeverity', $t_oids, 'CISCO-ENTITY-SENSOR-MIB');
    echo ' entSensorThresholdRelation';
    $t_oids = snmpwalk_cache_twopart_oid($device, 'entSensorThresholdRelation', $t_oids, 'CISCO-ENTITY-SENSOR-MIB');
    echo ' entSensorThresholdValue';
    $t_oids = snmpwalk_cache_twopart_oid($device, 'entSensorThresholdValue', $t_oids, 'CISCO-ENTITY-SENSOR-MIB');

    d_echo($oids);

    $entitysensor['voltsDC'] = 'voltage';
    $entitysensor['voltsAC'] = 'voltage';
    $entitysensor['amperes'] = 'current';
    $entitysensor['watt'] = 'power';
    $entitysensor['hertz'] = 'freq';
    $entitysensor['percentRH'] = 'humidity';
    $entitysensor['rpm'] = 'fanspeed';
    $entitysensor['celsius'] = 'temperature';
    $entitysensor['watts'] = 'power';
    $entitysensor['dBm'] = 'dbm';

    if (is_array($oids)) {
        foreach ($oids as $index => $entry) {
            // echo("[" . $entry['entSensorType'] . "|" . $entry['entSensorValue']. "|" . $index . "]");
            if (isset($entry['entSensorType'], $entry['entSensorValue'], $entitysensor[$entry['entSensorType']]) && $entitysensor[$entry['entSensorType']] && is_numeric($entry['entSensorValue']) && is_numeric($index)) {
                $group = null;
                $entPhysicalIndex = $index;
                if ($entity_array[$index]['entPhysicalName'] || $device['os'] == 'iosxr') {
                    $descr = rewrite_entity_descr($entity_array[$index]['entPhysicalName']);
                } else {
                    $descr = rewrite_entity_descr($entity_array[$index]['entPhysicalDescr']);
                }

                // Set description based on measured entity if it exists
                if (isset($entry['entSensorMeasuredEntity']) && is_numeric($entry['entSensorMeasuredEntity']) && $entry['entSensorMeasuredEntity']) {
                    $measured_descr = $entity_array[$entry['entSensorMeasuredEntity']]['entPhysicalName'];
                    if (! $measured_descr) {
                        $measured_descr = $entity_array[$entry['entSensorMeasuredEntity']]['entPhysicalDescr'];
                    }

                    $descr = $measured_descr . ' - ' . $descr;
                }

                // Bit dirty also, clean later
                $descr = str_replace('Temp: ', '', $descr);
                $descr = str_ireplace(' temperature', '', $descr);
                $descr = trim($descr);

                $oid = '.1.3.6.1.4.1.9.9.91.1.1.1.1.4.' . $index;
                $current = $entry['entSensorValue'];
                $type = $entitysensor[$entry['entSensorType']];

                // echo("$index : ".$entry['entSensorScale']."|");
                // FIXME this stuff is foul
                if ($entry['entSensorScale'] == 'nano') {
                    $divisor = '1000000000';
                    $multiplier = '1';
                }

                if ($entry['entSensorScale'] == 'micro') {
                    $divisor = '1000000';
                    $multiplier = '1';
                }

                if ($entry['entSensorScale'] == 'milli') {
                    $divisor = '1000';
                    $multiplier = '1';
                }

                if ($entry['entSensorScale'] == 'units') {
                    $divisor = '1';
                    $multiplier = '1';
                }

                if ($entry['entSensorScale'] == 'kilo') {
                    $divisor = '1';
                    $multiplier = '1000';
                }

                if ($entry['entSensorScale'] == 'mega') {
                    $divisor = '1';
                    $multiplier = '1000000';
                }

                if ($entry['entSensorScale'] == 'giga') {
                    $divisor = '1';
                    $multiplier = '1000000000';
                }

                if (is_numeric($entry['entSensorPrecision'])
                        && $entry['entSensorPrecision'] > '0'
                        // Workaround for a Cisco SNMP bug
                        && $entry['entSensorPrecision'] != '1615384784'
                ) {
                    // Use precision value to determine decimal point place on returned value, then apply divisor
                    $divisor = (10 ** $entry['entSensorPrecision']) * $divisor;
                }

                $current = ($current * $multiplier / $divisor);

                // Set thresholds to null
                $limit = null;
                $limit_low = null;
                $warn_limit = null;
                $warn_limit_low = null;

                // Check thresholds for this entry (bit dirty, but it works!)
                if (isset($t_oids[$index]) && is_array($t_oids[$index])) {
                    foreach ($t_oids[$index] as $t_index => $key) {
                        // Skip invalid treshold values
                        if (! isset($key['entSensorThresholdValue']) || $key['entSensorThresholdValue'] == '-32768') {
                            continue;
                        }
                        // Critical Limit
                        if (($key['entSensorThresholdSeverity'] == 'major' || $key['entSensorThresholdSeverity'] == 'critical') && ($key['entSensorThresholdRelation'] == 'greaterOrEqual' || $key['entSensorThresholdRelation'] == 'greaterThan')) {
                            $limit = ($key['entSensorThresholdValue'] * $multiplier / $divisor);
                        }

                        if (($key['entSensorThresholdSeverity'] == 'major' || $key['entSensorThresholdSeverity'] == 'critical') && ($key['entSensorThresholdRelation'] == 'lessOrEqual' || $key['entSensorThresholdRelation'] == 'lessThan')) {
                            $limit_low = ($key['entSensorThresholdValue'] * $multiplier / $divisor);
                        }

                        // Warning Limit
                        if ($key['entSensorThresholdSeverity'] == 'minor' && ($key['entSensorThresholdRelation'] == 'greaterOrEqual' || $key['entSensorThresholdRelation'] == 'greaterThan')) {
                            $warn_limit = ($key['entSensorThresholdValue'] * $multiplier / $divisor);
                        }

                        if ($key['entSensorThresholdSeverity'] == 'minor' && ($key['entSensorThresholdRelation'] == 'lessOrEqual' || $key['entSensorThresholdRelation'] == 'lessThan')) {
                            $warn_limit_low = ($key['entSensorThresholdValue'] * $multiplier / $divisor);
                        }
                    }//end foreach
                }//end if

                // If temperature sensor, set low thresholds to -1 and -5. Many sensors don't return low thresholds, therefore LibreNMS takes the runtime low
                // Also changing 0 values (not just null) as Libre loses these somewhere along the line and shows an empty value in the Web UI
                if ($type == 'temperature') {
                    if ($warn_limit_low == 0) {
                        $warn_limit_low = -1;
                    }
                    if ($limit_low == 0) {
                        $limit_low = -5;
                    }
                }

                // End Threshold code
                $ok = true;

                if ($current == '-127' || $descr == '') {
                    $ok = false;
                }

                if ($ok) {
                    $phys_index = $entity_array[$index]['entPhysicalContainedIn'];
                    $tmp_ifindex = 0;
                    while ($phys_index != 0) {
                        if ($index === $phys_index) {
                            break;
                        }

                        $entPhysicalClass = $entity_array[$phys_index]['entPhysicalClass'];
                        $entPhysicalName = $entity_array[$phys_index]['entPhysicalName'];
                        $transceivers = \App\Models\Transceiver::where('device_id', $device['device_id'])->where('index', '=', $phys_index)->first();
                        if (! empty($transceivers)) {
                            // If we already have a mapping done in transceivers, let's use it.
                            $entPhysicalIndex = $phys_index;
                            $entry['entSensorMeasuredEntity'] = 'ports';
                            $group = 'transceiver';
                            break;
                        }
                        //either sensor is contained by a port class entity.
                        if ($entPhysicalClass === 'port') {
                            $entAliasMappingIdentifier = $entity_array[$phys_index][0]['entAliasMappingIdentifier'];
                            if (Str::contains($entAliasMappingIdentifier, 'ifIndex.')) {
                                [, $tmp_ifindex] = explode('.', $entAliasMappingIdentifier);
                            }
                            break;
                            //or sensor entity has a parent entity with module class and entPhysicalName set to an existing ifName.
                        } elseif ($entPhysicalClass === 'module' && array_key_exists($entPhysicalName, $port_reverse_array)) {
                            $tmp_ifindex = $port_reverse_array[$entPhysicalName]['ifIndex'];
                            break;
                        } else {
                            $phys_index = $entity_array[$phys_index]['entPhysicalContainedIn'];
                        }
                    }
                    if ($tmp_ifindex != 0) {
                        $port_id = PortCache::getIdFromIfIndex($tmp_ifindex, $device['device_id']);
                        if ($port_id) {
                            $entPhysicalIndex = $phys_index;
                            $entry['entSensorMeasuredEntity'] = 'ports';
                            $group = 'transceiver';
                        }
                    }

                    discover_sensor(null, $type, $device, $oid, $index, 'cisco-entity-sensor', ucwords($descr), $divisor, $multiplier, $limit_low, $warn_limit_low, $warn_limit, $limit, $current, 'snmp', $entPhysicalIndex, $entry['entSensorMeasuredEntity'] ?? null, null, $group);
                    //Cisco IOS-XR : add a fake sensor to graph as dbm
                    if ($type == 'power' and $device['os'] == 'iosxr' and (preg_match('/power (R|T)x/i', $descr) or preg_match('/(R|T)x Power/i', $descr) or preg_match('/(R|T)x Lane/i', $descr))) {
                        // convert Watts to dbm
                        $user_func = 'mw_to_dbm';
                        $type = 'dbm';
                        $multiplier = 1000;
                        $limit_low = isset($limit_low) ? round(mw_to_dbm($limit_low * $multiplier), 3) : null;
                        $warn_limit_low = isset($limit_low) ? round(mw_to_dbm($warn_limit_low * $multiplier), 3) : null;
                        $warn_limit = isset($limit_low) ? round(mw_to_dbm($warn_limit * $multiplier), 3) : null;
                        $limit = isset($limit_low) ? round(mw_to_dbm($limit * $multiplier), 3) : null;
                        $current = mw_to_dbm($current * $multiplier);
                        //echo("\n".$valid['sensor'].", $type, $device, $oid, $index, 'cisco-entity-sensor', $descr, $divisor, $multiplier, $limit_low, $warn_limit_low, $warn_limit, $limit, $current, $user_func");
                        discover_sensor(null, $type, $device, $oid, $index, 'cisco-entity-sensor', $descr, $divisor, $multiplier, $limit_low, $warn_limit_low, $warn_limit, $limit, $current, 'snmp', $entPhysicalIndex, $entry['entSensorMeasuredEntity'] ?? null, $user_func, $group);
                    }
                }

                $cisco_entity_temperature = 1;
                unset($limit, $limit_low, $warn_limit, $warn_limit_low);
            }//end if
        }//end foreach
    }//end if
    unset(
        $entity_array
    );

    foreach (array_flip($entitysensor) as $type) {
        app('sensor-discovery')->sync(sensor_class: $type, poller_type: 'snmp');
    }
}//end if
