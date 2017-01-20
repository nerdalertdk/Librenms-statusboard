<?php
/**
 * StatusBoard
 *
 * Towel Software
 * www.towel.dk
 * © 2016
 *
 * This work is licensed under the Creative Commons Attribution 4.0 International License. 
 * To view a copy of this license, visit https://creativecommons.org/licenses/by/4.0/
 */

// Comment out to disable errors
ini_set('display_errors', 1);
ini_set('display_startup_errors', 1);
error_reporting(E_ALL);

// Change if you don't live in Denmark
date_default_timezone_set('Europe/Copenhagen');

class Status
{
    var $token  = null;
    var $url        = null;
    
    /*
        START
    */
    
    public function setUrl($url)
    {
        $this->url = filter_var($url , FILTER_VALIDATE_URL);
    }
    public function setToken($token)
    {
        $this->token = $token;
    }
    
    /*
        NMS API unc.
    */

    public function getServer($id)
    {
        $url = $this->url."/api/v0/devices/" . $id;
        return $this->curlGet($url);
    }
    
    public function getServerServices($id)
    {
        $url = $this->url."/api/v0/services/" . $id;
        return $this->curlGet($url);
    }
    
    public function getServerList()
    {
        $url = $this->url."/api/v0/devices?order=hostname";
        return $this->curlGet($url);
    }
    
    public function getServersInGroup($group)
    {
        $url = $this->url."/api/v0/devicegroups/" . $group;
        return $this->curlGet($url);
    }
    
    public function printStatus($devices)
    {   
        
        $return =
        '
            <table class="table table-hover table-striped table-responsive" >
                <thead> <tr> <th colspan="2">Server</th> <th>Status</th> <th>Uptime</th></tr></thead><tbody>
                
        ' . PHP_EOL;
        foreach($devices->devices AS $device)
        {
            
            $server = $this->getServer($device->device_id);
            
            //print_r($server->devices[0]);
            
            if($server->devices[0]->status)
            {
                $name = ($server->devices[0]->purpose) ? $server->devices[0]->purpose : $server->devices[0]->hostname;
                $return .= '<tr class="success"><td colspan="2">' . $name . '</td> <td>Online</td> <td>'. $this->secondsToTime($server->devices[0]->uptime) .'</td> </tr>'. PHP_EOL;
                
                $services = $this->getServerServices($device->device_id);
                if($services->count > 0)
                {
                    //print_r($services);
                    foreach($services->services[0] AS $service)
                    {
                                           
                        //print_r($service);
                        // service_changed
                        $status = ($service->service_status == 0) ? 'success' : 'danger animated flash';
                        $return .=
                        '
                            <tr class="'. $status .'">
                                <td class="serviceRow" ></td>
                                <td class="serviceRow">' . $service->service_desc . '</td>
                                <td class="serviceRow"><span class="hidden-xs">'. $service->service_message .'</span></td>
                                <td class="serviceRow">'. $this->secondsToTime( time() - $service->service_changed ) .'</td>
                            </tr>
                        '. PHP_EOL;
                    }
                }
            }
            else
            {
                //last_discovered
                $return .= '<tr class="danger animated flash run-animation-5"><td colspan="2">' . ($server->devices[0]->purpose) . '</td> <td>Offline</td> <td>Last seen '.$this->relativeDate($server->devices[0]->last_discovered).'</td> </tr>'. PHP_EOL;
            }
        }
        $return .= "</tbody></table>" . PHP_EOL;
        return $return;
    }
     
    /*
        PRIVATE
    */
    private function secondsToTime($ptime)
    {
        $estimate_time = ($ptime);
        $condition = array( 
                    12 * 30 * 24 * 60 * 60  =>  'year',
                    30 * 24 * 60 * 60       =>  'month',
                    24 * 60 * 60            =>  'day',
                    60 * 60                 =>  'hour',
                    60                      =>  'minute',
                    1                       =>  'second'
        );

        foreach( $condition as $secs => $str )
        {
            $d = $estimate_time / $secs;

            if( $d >= 1 )
            {
                $r = round( $d );
                return $r . ' ' . $str . ( $r > 1 ? 's' : '' ) . ' ';
            }
        }
    }
    
    private function relativeDate( $ptime )
    {
        $estimate_time = time() - strtotime($ptime);
        if( $estimate_time < 1 )
        {
            return 'less than 1 second ago';
        }

        $condition = array( 
                    12 * 30 * 24 * 60 * 60  =>  'year',
                    30 * 24 * 60 * 60       =>  'month',
                    24 * 60 * 60            =>  'day',
                    60 * 60                 =>  'hour',
                    60                      =>  'minute',
                    1                       =>  'second'
        );

        foreach( $condition as $secs => $str )
        {
            $d = $estimate_time / $secs;

            if( $d >= 1 )
            {
                $r = round( $d );
                return $r . ' ' . $str . ( $r > 1 ? 's' : '' ) . ' ago';
            }
        }
    }
    
    private function curlGet($url)
    {
        $curl = curl_init($url);
        curl_setopt($curl, CURLOPT_HEADER, false);
        curl_setopt($curl, CURLOPT_HTTPHEADER, array("X-Auth-Token: ".$this->token));
        curl_setopt($curl, CURLOPT_RETURNTRANSFER, true);
        curl_setopt($curl, CURLOPT_CONNECTTIMEOUT ,0);
        curl_setopt($curl, CURLOPT_TIMEOUT, 30);
        curl_setopt($curl, CURLOPT_SSL_VERIFYPEER,false);

        $json_response = curl_exec($curl);
        $status = curl_getinfo($curl, CURLINFO_HTTP_CODE);

        if ($status != 200) {
            die("Error: call to URL ".$_SERVER["PHP_SELF"]." failed with status $status, response $json_response, curl_error " . curl_error($curl) . ", curl_errno " . curl_errno($curl));
        }
        curl_close($curl);

        return json_decode($json_response);
    }
    
}

$status = new Status();
$status->setUrl('https://SITE URL');
$status->setToken('API TOKEN');

// if you only what a group to be visible use this
//$devices = $status->getServersInGroup('GROUP NAME');

// Listes all servers including services
$devices = $status->getServerList();

?>

<html>
    <head>
        <title>DaniaGaming - Statusboard</title>
        <meta http-equiv="refresh" content="300">
        <meta name="viewport" content="width=device-width, initial-scale=1, maximum-scale=1, user-scalable=no">

        <!-- Latest compiled and minified CSS -->
        <link rel="stylesheet" href="https://cdnjs.cloudflare.com/ajax/libs/twitter-bootstrap/3.3.7/css/bootstrap.min.css" integrity="sha256-916EbMg70RQy9LHiGkXzG8hSg9EdNy97GazNG/aiY1w=" crossorigin="anonymous" />

        <!-- Optional theme -->
        <link rel="stylesheet" href="https://cdnjs.cloudflare.com/ajax/libs/twitter-bootstrap/3.3.7/css/bootstrap-theme.min.css" integrity="sha256-ZT4HPpdCOt2lvDkXokHuhJfdOKSPFLzeAJik5U/Q+l4=" crossorigin="anonymous" />
        <link rel="stylesheet" href="https://cdnjs.cloudflare.com/ajax/libs/animate.css/3.5.2/animate.min.css">
        <link rel="stylesheet" href="https://cdnjs.cloudflare.com/ajax/libs/bootswatch/3.3.7/darkly/bootstrap.min.css">
        <style type="text/css">
            .serviceRow
            {
                line-height: 6px !important;
                font-width: normal;
                font-size: .9em;
            }
            .run-animation-5
            {
                animation-duration: 3s;
                animation-delay: 2s;
                animation-iteration-count: 2;
            }
        </style>
        
    </head>
    <body>
        <?=$status->printStatus($devices)?>
        <div class="text-center text-muted">
            <small><a class="text-muted" href="http://www.librenms.org/">Librenms</a> statusboard by <a class="text-muted" href="http://towel.dk">Towel.dk</a> - <a class="text-muted" rel="license" href="http://creativecommons.org/licenses/by/4.0/">License</a> <span id="counter"></span></small>
        </div>
        <script src="https://cdnjs.cloudflare.com/ajax/libs/twitter-bootstrap/3.3.7/js/bootstrap.min.js" integrity="sha256-U5ZEeKfGNOja007MMD3YBI0A3OSZOQbeG6z2f2Y0hu8=" crossorigin="anonymous"></script>
        <script type="text/javascript">
            function startTimer(duration, display)
            {
                var timer = duration, minutes, seconds;
                setInterval(function () 
                {
                    minutes = parseInt(timer / 60, 10)
                    seconds = parseInt(timer % 60, 10);

                    minutes = minutes < 10 ? "0" + minutes : minutes;
                    seconds = seconds < 10 ? "0" + seconds : seconds;

                    document.getElementById(display).innerHTML = " - Refresh in: " + (minutes + ":" + seconds);

                    if (--timer < 0) {
                        timer = duration;
                    }
                }, 1000);
            }
            startTimer(60*5,"counter");
        </script>
    </body>
</html>
