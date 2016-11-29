<?php
class SecurityUtil {
  /**
     * $resource - a string representing the page name or URL or any other key you want to use to throttle requests for.
   * $rate - an int specifying the maximum number of allowed access requests per window period
   * $window - number of seconds representing your throttle window
   * $any_user - if false, the $_SERVER['REMOTE_ADDR'] IP of the client making the request is used to throttle number of requests
   * only for that client. If set to true, then the resource is throttled globally against requests from ANY user IP (good for DDoS prevention).
   *
   * returns nothing if the user should not be throttled, throws an Exception if user has exceeded allowed number of requests.
   *
   * NOTE: The constant THROTTLE_FILE needs to be defined, which should point to the full path on the server to a text file that is writable by Apache.
   * Usage: just call this function whenever you process a request that you want to throttle, passing in the request-url (or some other identifying key),
   * and the throttle parameters. For example:
   * try {
   *     SecurityUtil::throttleResource('/admin/login-page', 5, 120);  //this limits the number of times a user can access the login page to 5 every 2 minutes.
   *     //if code gets to here, no exception thrown, which means user has not exceeded allowed number of attempts, so process the login (or whatever request)
   * } catch (Exception $e) {
   *     echo "Number of allowed login attempts exceeded. Please try again later.";
   *     exit;
   * }
   */
    public static function throttleResource($resource, $rate = 7, $window = 60, $any_user = false) {
        //load the cache from file
        define('THROTTLE_FILE', './log/secure.log');
        $cache = file_exists(THROTTLE_FILE) ? self::varFromFile(THROTTLE_FILE) : array();    //load php array from file
    	$user = $any_user ? '0.0.0.0' : $_SERVER['REMOTE_ADDR'];

        //initialize cache variables
        if(!isset($cache[$user])) $cache[$user] = array();
        if(!isset($cache[$user][$resource])) $cache[$user][$resource] = array();

        //timestamp the cache resource access
        $cache[$user][$resource][] = time();
        //only hold the required number of timestamps
        if(count($cache[$user][$resource]) > $rate) array_shift($cache[$user][$resource]);

        //check if resource has exceeded allowed access requests
        $deny_access = false;
        $attempts = $cache[$user][$resource];
        if(count($attempts) < $rate) $deny_access = false;      //didn't exceed allowed access requests
        elseif($attempts[0] + $window > time()) $deny_access = true;  //else matched or exceeded allowed requests, and oldest access was within the window
        else $deny_access = false;                    //otherwise oldest access was not within window, so allow

        //cleanup the cache so it doesn't get too large over time
        foreach($cache as $ip=>$resources) {                    //for each IP address record
            foreach($resources as $res=>$attempts) {            //for each IP resource bucket
                if($attempts) {                                 //if resource access record exist
                    if($attempts[count($attempts)-1] + $window < time()) {  //if latest record is older than window
                        unset($cache[$ip][$res]);                           //irrelevant, delete the record
                    }
                }
            }
            if(!$resources) unset($cache[$ip]);                 //if the IP record has no resource buckets, delete it
        }

        //store the cache back to file
        self::varToFile(THROTTLE_FILE, $cache);

        if($deny_access) throw new Exception('Maximum access requests exceeded.');
    }

  public static function varToFile($filename, $var) {
        $data = gzcompress(serialize($var),9);
        $res = self::stringToFile($filename, $data);
        return $res;
    }

    public static function varFromFile($filename) {
        if(file_exists($filename)) {
            $data = unserialize(gzuncompress(file_get_contents($filename)));
            if($data) return $data;
            else return false;
        } else return false;
    }

  public static function fileToString($filename) {
        return file_get_contents($filename);
    }

    public static function stringToFile($filename, $data) {
        $file = fopen ($filename, "w");
        fwrite($file, $data);
        fclose ($file);
        return true;
    }
}
?>

