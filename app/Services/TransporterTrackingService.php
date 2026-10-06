<?php

namespace App\Services;

use Illuminate\Support\Facades\Cache;

class TransporterTrackingService
{
    /**
     * Resolve live tracking URL for a given transporter and LR / Bilty / Docket number
     */
    public static function getTrackingUrl(?string $transporterName, ?string $lrNumber): ?string
    {
        $cleanLr = trim($lrNumber ?? '');
        if (empty($cleanLr)) {
            return null;
        }

        $cleanTransporter = strtolower(trim($transporterName ?? ''));

        // 1. V-Trans (India) Ltd. - Verified: Pre-fills DocketNumber into official tracking form
        if (str_contains($cleanTransporter, 'v-trans') || str_contains($cleanTransporter, 'vtrans') || str_contains($cleanTransporter, 'v trans')) {
            return "https://vtransgroup.com/tools/track-trace/?DocketNumber={$cleanLr}";
        }

        // 2. TCI Express / TCI Freight
        if (str_contains($cleanTransporter, 'tci')) {
            return "https://www.tciexpress.in/tracking.aspx?consign={$cleanLr}";
        }

        // 3. SafeXpress
        if (str_contains($cleanTransporter, 'safexpress') || str_contains($cleanTransporter, 'safe xpress')) {
            return "https://www.safexpress.com/track-and-trace?waybill={$cleanLr}";
        }

        // 4. VRL Logistics
        if (str_contains($cleanTransporter, 'vrl')) {
            return 'https://www.vrlgroup.in/Tracking/TrackShipment';
        }

        // 5. Gati
        if (str_contains($cleanTransporter, 'gati')) {
            return "https://www.gati.com/track-and-trace/?dktno={$cleanLr}";
        }

        // 6. DTDC Express
        if (str_contains($cleanTransporter, 'dtdc')) {
            return "https://www.dtdc.in/tracking/tracking_results.asp?Ttype=awb_no&strCnno={$cleanLr}";
        }

        // 7. Blue Dart
        if (str_contains($cleanTransporter, 'blue dart') || str_contains($cleanTransporter, 'bluedart')) {
            return "https://www.bluedart.com/tracking?track={$cleanLr}";
        }

        // 8. ARC (Associated Road Carriers)
        if (str_contains($cleanTransporter, 'arc') || str_contains($cleanTransporter, 'associated road')) {
            return 'https://www.arcindia.net/';
        }

        // 9. Avinash Cargo Private Limited (ACPL Cargo) - Verified official portal
        if (str_contains($cleanTransporter, 'avinash') || str_contains($cleanTransporter, 'acpl')) {
            return 'https://www.acplcargo.com/GCTRACKING.php';
        }

        // 10. Navata Road Transport
        if (str_contains($cleanTransporter, 'navata')) {
            return "https://navata.com/track-consignment/?lrno={$cleanLr}";
        }

        // 11. Generic Smart Cargo Tracker (Direct Google Query with Transporter and LR)
        $query = urlencode(($transporterName ? "{$transporterName} " : '')."LR tracking {$cleanLr}");

        return "https://www.google.com/search?q={$query}";
    }

    /**
     * Fetch live shipment tracking details directly from carrier systems in background
     */
    public static function fetchLiveTrackingDetails(?string $transporterName, ?string $lrNumber, $shipment = null, bool $forceRefresh = false): array
    {
        $cleanLr = trim($lrNumber ?? '');
        $cleanTransporter = strtolower(trim($transporterName ?? ''));
        $externalUrl = self::getTrackingUrl($transporterName, $lrNumber);

        $defaultResult = [
            'success' => true,
            'mode' => 'PORTAL',
            'transporter' => $transporterName ?: 'Carrier',
            'lr_number' => $cleanLr,
            'origin' => $shipment?->salesOrder?->customer?->city ?? 'Factory Godown',
            'destination' => $shipment?->destination ?? ($shipment?->salesOrder?->customer?->city ?? 'Destination'),
            'current_location' => null,
            'booking_date' => $shipment?->shipment_date?->format('d/m/Y') ?? null,
            'expected_date' => null,
            'delivered_date' => null,
            'vehicle_no' => $shipment?->vehicle_number ?? null,
            'packages' => null,
            'weight' => null,
            'current_status' => $shipment ? "Shipment status in ERP: {$shipment->status}" : 'Tracking in transit',
            'stage' => ($shipment?->status === 'DELIVERED') ? 'DELIVERED' : 'IN_TRANSIT',
            'scans' => [],
            'external_url' => $externalUrl,
            'can_sync' => ($shipment !== null && $shipment->status !== 'DELIVERED'),
            'shipment_id' => $shipment?->id,
        ];

        if (empty($cleanLr)) {
            $defaultResult['success'] = false;
            $defaultResult['current_status'] = 'No LR or Bilty number provided for this consignment';

            return $defaultResult;
        }

        $cacheKey = 'transporter_live_track_'.md5($cleanTransporter.'_'.$cleanLr);
        if ($forceRefresh) {
            Cache::forget($cacheKey);
        } else {
            $cached = Cache::get($cacheKey);
            if ($cached && is_array($cached)) {
                $cached['shipment_id'] = $shipment?->id;
                $cached['can_sync'] = ($shipment !== null && $shipment->status !== 'DELIVERED');

                return $cached;
            }
        }

        // 1. V-TRANS DIRECT LIVE BACKEND API
        if (str_contains($cleanTransporter, 'v-trans') || str_contains($cleanTransporter, 'vtrans') || str_contains($cleanTransporter, 'v trans')) {
            try {
                $ch = curl_init('https://vtransgroup.com/api/trackTraceAPINew.php');
                curl_setopt_array($ch, [
                    CURLOPT_RETURNTRANSFER => true,
                    CURLOPT_POST => true,
                    CURLOPT_POSTFIELDS => http_build_query(['Gc_No' => $cleanLr]),
                    CURLOPT_IPRESOLVE => CURL_IPRESOLVE_V4,
                    CURLOPT_TIMEOUT => 8,
                    CURLOPT_CONNECTTIMEOUT => 5,
                    CURLOPT_SSL_VERIFYPEER => false,
                    CURLOPT_SSL_VERIFYHOST => false,
                    CURLOPT_HTTPHEADER => [
                        'X-Requested-With: XMLHttpRequest',
                        'Origin: https://vtransgroup.com',
                        'Referer: https://vtransgroup.com/tools/track-trace/',
                    ],
                    CURLOPT_USERAGENT => 'Mozilla/5.0 (Windows NT 10.0; Win64; x64) AppleWebKit/537.36 (KHTML, like Gecko) Chrome/133.0.0.0 Safari/537.36',
                ]);
                $html = curl_exec($ch);
                $httpCode = curl_getinfo($ch, CURLINFO_HTTP_CODE);
                curl_close($ch);

                if ($httpCode === 200 && ! empty($html) && strlen($html) > 500) {
                    $liveData = $defaultResult;
                    $liveData['mode'] = 'LIVE';

                    // Current Status
                    if (preg_match('/<b class="value">([^<]+)<\/b>/i', $html, $m)) {
                        $liveData['current_status'] = trim($m[1]);
                    }

                    // Origin / Destination / Date
                    if (preg_match('/<th>From<\/th>.*?<td>([^<]+)<\/td>/is', $html, $m)) {
                        $liveData['origin'] = trim($m[1]);
                    }
                    if (preg_match('/<th>To<\/th>.*?<td>([^<]+)<\/td>/is', $html, $m)) {
                        $liveData['destination'] = trim($m[1]);
                    }
                    if (preg_match('/<th>Docket Date<\/th>.*?<td>([^<]+)<\/td>/is', $html, $m)) {
                        $liveData['booking_date'] = trim($m[1]);
                    }

                    // Stage determination
                    if (preg_match('/class="list\s+out-of-delivery[^"]*active/i', $html)) {
                        $liveData['stage'] = 'DELIVERED';
                    } elseif (preg_match('/class="list\s+pickup-point[^"]*active/i', $html)) {
                        $liveData['stage'] = 'OUT_FOR_DELIVERY';
                    } elseif (preg_match('/class="list\s+arived-location[^"]*active/i', $html)) {
                        $liveData['stage'] = 'ARRIVED';
                    } elseif (preg_match('/class="list\s+in-transist[^"]*active/i', $html)) {
                        $liveData['stage'] = 'IN_TRANSIT';
                    } else {
                        $liveData['stage'] = 'BOOKED';
                    }

                    // Scan details
                    if (preg_match('/<div id="StatusAndScan".*?<tbody>(.*?)<\/tbody>/is', $html, $tbody)) {
                        if (preg_match_all('/<tr>\s*<td>(.*?)<\/td>\s*<td>(.*?)<\/td>\s*<td>(.*?)<\/td>\s*<td>(.*?)<\/td>\s*<\/tr>/is', $tbody[1], $rows, PREG_SET_ORDER)) {
                            foreach ($rows as $r) {
                                $liveData['scans'][] = [
                                    'location' => trim(strip_tags($r[1])),
                                    'activity' => trim(strip_tags($r[2])),
                                    'date' => trim(strip_tags($r[3])),
                                    'time' => trim(strip_tags($r[4])),
                                ];
                            }
                        }
                    }

                    $ttl = ($liveData['stage'] === 'DELIVERED') ? 86400 : 180;
                    Cache::put($cacheKey, $liveData, $ttl);

                    return $liveData;
                }
            } catch (\Throwable $e) {
                // Fall back gracefully
            }
        }

        // 2. AVINASH CARGO (ACPL) DIRECT LIVE BACKEND
        if (str_contains($cleanTransporter, 'avinash') || str_contains($cleanTransporter, 'acpl')) {
            $acplData = self::fetchAcplLiveTracking($cleanLr);
            if ($acplData) {
                $liveData = array_merge($defaultResult, $acplData);
                $liveData['mode'] = 'LIVE';
                $liveData['shipment_id'] = $shipment?->id;
                $liveData['can_sync'] = ($shipment !== null && $shipment->status !== 'DELIVERED');

                $ttl = ($liveData['stage'] === 'DELIVERED') ? 86400 : 180;
                Cache::put($cacheKey, $liveData, $ttl);

                return $liveData;
            }
        }

        return $defaultResult;
    }

    /**
     * Internal direct crawler for Avinash Cargo (ACPL) tracking backend
     */
    protected static function fetchAcplLiveTracking(string $cleanLr): ?array
    {
        try {
            // Step 1: Obtain session cookies and CSRF token from GCTRACKING.php
            $ch = curl_init('https://www.acplcargo.com/GCTRACKING.php');
            curl_setopt_array($ch, [
                CURLOPT_RETURNTRANSFER => true,
                CURLOPT_HEADER => true,
                CURLOPT_IPRESOLVE => CURL_IPRESOLVE_V4,
                CURLOPT_TIMEOUT => 8,
                CURLOPT_CONNECTTIMEOUT => 5,
                CURLOPT_SSL_VERIFYPEER => false,
                CURLOPT_SSL_VERIFYHOST => false,
                CURLOPT_USERAGENT => 'Mozilla/5.0 (Windows NT 10.0; Win64; x64) AppleWebKit/537.36 (KHTML, like Gecko) Chrome/133.0.0.0 Safari/537.36',
                CURLOPT_HTTPHEADER => [
                    'Accept: text/html,application/xhtml+xml,application/xml;q=0.9,*/*;q=0.8',
                    'Accept-Language: en-US,en;q=0.9',
                ],
            ]);
            $raw = curl_exec($ch);
            $headerSize = curl_getinfo($ch, CURLINFO_HEADER_SIZE);
            $code1 = curl_getinfo($ch, CURLINFO_HTTP_CODE);
            curl_close($ch);

            if ($code1 !== 200 || empty($raw)) {
                return null;
            }

            $headers = substr($raw, 0, $headerSize);
            $body = substr($raw, $headerSize);

            // Extract all Set-Cookie values
            preg_match_all('/^Set-Cookie:\s*([^;]+)/mi', $headers, $matches);
            $cookieHeader = implode('; ', $matches[1] ?? []);

            // Extract CSRF token and Request token
            preg_match('/id="csrf_token"\s*value="([^"]+)"/', $body, $m);
            $csrfToken = $m[1] ?? null;

            preg_match('/id="request_token"\s*value="([^"]+)"/', $body, $m2);
            $requestToken = $m2[1] ?? null;

            if (! $csrfToken || empty($cookieHeader)) {
                return null;
            }

            // Step 2: POST to gc_tracking.php
            $postData = ['gcnumber' => $cleanLr];
            if ($requestToken) {
                $postData['request_token'] = $requestToken;
            }

            $ch2 = curl_init('https://www.acplcargo.com/gc_tracking.php');
            curl_setopt_array($ch2, [
                CURLOPT_RETURNTRANSFER => true,
                CURLOPT_POST => true,
                CURLOPT_POSTFIELDS => http_build_query($postData),
                CURLOPT_IPRESOLVE => CURL_IPRESOLVE_V4,
                CURLOPT_TIMEOUT => 12,
                CURLOPT_CONNECTTIMEOUT => 5,
                CURLOPT_SSL_VERIFYPEER => false,
                CURLOPT_SSL_VERIFYHOST => false,
                CURLOPT_USERAGENT => 'Mozilla/5.0 (Windows NT 10.0; Win64; x64) AppleWebKit/537.36 (KHTML, like Gecko) Chrome/133.0.0.0 Safari/537.36',
                CURLOPT_HTTPHEADER => [
                    'Accept: */*',
                    'Accept-Language: en-US,en;q=0.9',
                    'Content-Type: application/x-www-form-urlencoded; charset=UTF-8',
                    'Cookie: '.$cookieHeader,
                    'Origin: https://www.acplcargo.com',
                    'Referer: https://www.acplcargo.com/GCTRACKING.php',
                    'Sec-Fetch-Dest: empty',
                    'Sec-Fetch-Mode: cors',
                    'Sec-Fetch-Site: same-origin',
                    'X-CSRF-Token: '.$csrfToken,
                    'X-ACPL-Client-Context: '.base64_encode('www.acplcargo.com|https://www.acplcargo.com/GCTRACKING.php'),
                    'X-Requested-With: XMLHttpRequest',
                ],
            ]);
            $html = curl_exec($ch2);
            $code2 = curl_getinfo($ch2, CURLINFO_HTTP_CODE);
            curl_close($ch2);

            if ($code2 !== 200 || empty($html) || strlen($html) < 200) {
                return null;
            }

            $result = [
                'origin' => null,
                'destination' => null,
                'current_location' => null,
                'booking_date' => null,
                'expected_date' => null,
                'delivered_date' => null,
                'current_status' => null,
                'vehicle_no' => null,
                'packages' => null,
                'weight' => null,
                'sender_address' => null,
                'receiver_address' => null,
                'stage' => 'IN_TRANSIT',
                'scans' => [],
            ];

            // 1. BOOKING MASTER TABLE
            if (preg_match('/BOOKING MASTER.*?<tbody>\s*<tr>(.*?)<\/tr>/is', $html, $m)) {
                preg_match_all('/<td>(.*?)<\/td>/is', $m[1], $tds);
                $vals = array_map(fn ($v) => trim(strip_tags($v)), $tds[1] ?? []);
                if (count($vals) >= 9) {
                    $result['origin'] = $vals[2] ?: null;
                    $result['destination'] = $vals[3] ?: null;
                    $result['current_location'] = $vals[4] ?: null;
                    $result['expected_date'] = $vals[5] ?: null;
                    $result['pickup_date'] = $vals[6] ?: null;
                    $result['current_status'] = $vals[7] ?: null;
                    $result['vehicle_no'] = $vals[8] ?: null;
                }
            }

            // 2. BOOKING DETAILS TABLE
            if (preg_match('/BOOKING DETAILS.*?<tbody>\s*<tr>(.*?)<\/tr>/is', $html, $m)) {
                preg_match_all('/<td>(.*?)<\/td>/is', $m[1], $tds);
                $vals = array_map(fn ($v) => trim(strip_tags($v)), $tds[1] ?? []);
                if (count($vals) >= 10) {
                    $result['booking_date'] = $vals[1] ?: null;
                    $result['packages'] = $vals[2] ?: null;
                    $result['sender_address'] = $vals[4] ?: null;
                    $result['receiver_address'] = $vals[6] ?: null;
                    $result['weight'] = $vals[8] ? ($vals[8].' kg') : null;
                    $result['delivered_date'] = $vals[9] ?: null;
                }
            }

            // 3. DISPATCH DETAILS (Scans)
            if (preg_match('/DISPATCH DETAILS.*?<tbody>(.*?)<\/tbody>/is', $html, $m)) {
                if (preg_match_all('/<tr>\s*<td>(.*?)<\/td>\s*<td>(.*?)<\/td>\s*<td>(.*?)<\/td>\s*<\/tr>/is', $m[1], $rows, PREG_SET_ORDER)) {
                    foreach ($rows as $r) {
                        $result['scans'][] = [
                            'location' => trim(strip_tags($r[2])),
                            'activity' => trim(strip_tags($r[3])),
                            'date' => trim(strip_tags($r[1])),
                            'time' => '',
                        ];
                    }
                }
            }

            // 4. Stage determination
            $statusStr = strtolower($result['current_status'] ?? '');
            if (str_contains($statusStr, 'deliver') || ! empty($result['delivered_date'])) {
                $result['stage'] = 'DELIVERED';
            } elseif (str_contains($statusStr, 'out for') || str_contains($statusStr, 'reached') || str_contains($statusStr, 'arrived')) {
                $result['stage'] = 'ARRIVED';
            } elseif (str_contains($statusStr, 'transit') || count($result['scans']) > 1) {
                $result['stage'] = 'IN_TRANSIT';
            } else {
                $result['stage'] = 'BOOKED';
            }

            return $result;
        } catch (\Throwable $e) {
            return null;
        }
    }
}
