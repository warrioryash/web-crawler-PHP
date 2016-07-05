<?php


/* 
 *		       GNU GENERAL PUBLIC LICENSE
 *		          Version 2, June 1991
 *
 *  Copyright (C) 2016 Yash Singh
 *
 *  This program is free software; you can redistribute it and/or modify
 *  it under the terms of the GNU General Public License as published by
 *  the Free Software Foundation; either version 2 of the License, or
 *  (at your option) any later version.
 *
 *  This program is distributed in the hope that it will be useful,
 *  but WITHOUT ANY WARRANTY; without even the implied warranty of
 *  MERCHANTABILITY or FITNESS FOR A PARTICULAR PURPOSE.  See the
 *  GNU General Public License for more details.
 *
 *  You should have received a copy of the GNU General Public License
 *  along with this program; if not, write to the Free Software
 *  Foundation, Inc., 51 Franklin St, Fifth Floor, Boston, MA  02110-1301 
 *  USA
 *
 *
 *  For questions regarding this license you can email me at 
 *  warrioryash@protonmail.com
 */

/* 
 *		               PHP WEB CRAWLER
 *	                  Sat, Aug 31 2013 04:58:51
 *
 *  1. This crawler will extract meta-data from HTML pages and store this 
 *     data in a MySQL database.
 *
 *  2. The crawler can be invoked from the commandline or via a GET/POST 
 *     request.
 */


// Start recording session variables
session_start();
// Enable error reporting
error_reporting(E_ALL);
// Create STOPWATCH to track execution time
$time_start = microtime(true);




// --------- START SCRIPT -----------------

// This object keeps track of the data source (POST, GET, etc)
$DSource = new DataSource();

// Now that the data source is known, the DataStream object will
// manipulate/process that data
$DStream = new DataStream($DSource->dataSource);

// For example, I will pull out a list of URLs from the incoming data
// Note: The GET or POST data should contain a field called listURL
// If not then search all of the POST, GET, command line data for URLs 
$ListOfURLs= $DStream->URLs_filter("listURL");

// Create the objects that will crawl the above list of URLs
$i=1;
foreach ($ListOfURLs as $url){
   $crawler = new Crawler($url);
   $crawler->rolling_curl($ListOfURLs, "parseData", $custom_options = null);
}

echo "<h4  style='color:red'>Total Number of Links Seen : ".count($crawler->seen)."</h4>";
echo "<h4 style='color:red'> Total number of pages downloaded: ".$crawler->pagesDownloaded."</h4>";

// --------- END SCRIPT -----------------




class DataSource
{
   // Store the method by which this script is being accessed (POST, GET, etc)
   public $method;
   // Store the data source here, that is incoming data from POST, GET, etc
   public $dataSource;    

   // This is the default constructor
   function __construct() {
      // Retarded PHP does not automatically set $argv as a global variable
      // Making it global makes it possible to access it from within classes
      global $argv;
      // How is this script being accessed?
      // The variable $_SERVER['REQUEST_METHOD'] will be set if
      // the access method is: POST, GET, PUT or HEAD
      if (isset($_SERVER['REQUEST_METHOD']))
         $this->method = $_SERVER['REQUEST_METHOD'];
      // If none of the above then check for access via command line
      elseif (isset($argv))
         $this->method="argv"; 

      // Go through all the possibilities to set the data source
      switch ($this->method) {
      case 'POST':
         $this->dataSource=&$_POST;
         break;
      case 'GET':
         $this->dataSource=&$_GET; 
         break;
      case 'PUT':
         $this->dataSource=&$_PUT;
         break;
      case 'HEAD':
         $this->dataSource=&$_HEAD;
         break; 
      case 'argv':
         $this->dataSource=&$argv;
         break;  
      default:
        $this->dataSource="";
        break;
      } // CLOSE switch ($method) {

   }// CLOSE function __construct() {

}// CLOSE class DataSource










// This class filters/processes data coming from various sources 
// (GET, POST, ARG, etc)

class DataStream
{

   // This is where incoming data is stored (from GET, POST, command line, etc)
   public $stream;


   
   // If a stream DataStream(someDataSource) is provided
   // attach it to the DataStream object:
   function __construct(&$someDataSource) {

      // Set the stream to $_POST, $_GET, $argv, etc.
      $this->stream = &$someDataSource;
   }



   // If no stream was declared when the DataStream object
   // was declared then here is a method to set the data stream later
   public function setDataSource(&$someDataSource) {

      // Set the stream to $_POST, $_GET, $argv, etc.
      return $this->stream = &$someDataSource;
   }



   // This function looks at stream data for an entry matching
   // $formElementName and returns data associated with the form element.
   public function URLs_filter($formElementName){

      // This is the filter which will be applied. In thise case
      // I will select URLs from the data source. Note the expression is
      // deliberately set to catch anything like abc.ef or www.abc.ef
      $reg_exUrl = "@((https?://)?([-\w]+\.[-\w\.]+)+\w(:\d+)?(/([-\w/_\.]*(\?\S+)?)?)*)@";
 
      $out=array();
      // In the data stream search for $formElementName
      if (isset($this->stream[$formElementName]))
         // it exists, now search this sub-section of the data stream for URLs
         preg_match_all($reg_exUrl,$this->stream[$formElementName], $out, PREG_PATTERN_ORDER);
      else{
         // Data stream is coming from the command line. Remove argv[0] since this contains
         // the name of the file.
         $stdata=array();
         for ($i=1; $i < count($this->stream); $i++)
            $stdata[]=$this->stream[$i];
           
    
         // it does not exist, now join the entire array into a giant string and search for
         // URLs
         preg_match_all($reg_exUrl,implode(" ", $stdata), $out, PREG_PATTERN_ORDER);  
      }       

      return $out[1];
    }


} // CLOSE class DataStream





class Crawler
{

   // Store the domains to crawl here
   public $domainToCrawl;

   public $counter;

   // The crawler keeps track of the URLs it has seen here
   public $seen = array();

   // The crawler keeps track of the URLs it has downloaded
   public $downloadedURL = array();

   // This is where the downloaded pages are stored
   public $pageData= array();  

   // The crawler counts the number of pages it has downloaded
   public $pagesDownloaded=0;


   // This is the default constructor
   function __construct($domain) {
      // Seed the crawler with a list of domains to crawl
      $this->domainToCrawl=$domain;
   }
 

   public function rolling_curl($urls, $callback, $custom_options = null) {

            $this->counter++;
      /* --- SECTION: PREPARE "seen" AND "notseen" LIST OF LINKS ---*/

      // Store those links here which have not been "seen" (downloaded) yet
      $notSeenLinks=array();
      // Check if all the incoming URLs ($urls) have already been seen.
      foreach ($urls as $url){ 

         // If a link has not been seen then add it to the NOT seen array ($notSeenLinks)
         if (!isset($this->seen[$url])){
            $surl=$url."/";
            // Retarded cURL changes the original URL by adding a trailing slash.
            // Consequently the 'seen' list of URLs has the 'trailing slash' version, not
            // the original. I create a version ($surl) with a slash and compare that...
            if (!isset($this->seen[$surl]))
               $notSeenLinks[]=$url;             
         }
      }
/*
      // TESTING: Print out the 'seen' and 'notseen' arrays
      // When enabled this will print out the seen array
      // watch it grow as more and more pages are downloaded
*/ 
         echo "\n++++++++++++++++++++++\n";
         echo "\n---------SEEN---------\n";
         print_r($this->seen);
         echo "\n---------NOT SEEN---------\n";
         print_r($notSeenLinks);
         echo "\n**********************\n";
           

      // In the check above if all the links have been seen then $notSeenLinks will 
      // be empty. If this is the case then RETURN -- END OF SEARCH.
      if (empty($notSeenLinks)){
         return;
      }
      // Else there are URLs that have not been seen/downloaded. Get them...
      // Count the total number of URLs
      $URL_count = count($notSeenLinks);


      /* --- SECTION: USE MULTI-THREADED cURL LIBRARY TO DOWNLOAD LINKS ---*/

      // Rolling Window: # of Pages to download at a time.
      // Make sure the rolling window isn't greater than the # of $notSeenLinks
      $rolling_window = 10;
      $rolling_window = (sizeof($notSeenLinks) < $rolling_window) ? sizeof($notSeenLinks) : $rolling_window;

      $master = curl_multi_init();
      $curl_arr = array();
      $page_data = array();

      // Set standard cURL options here and add additional curl options ($options), if any
       $std_options = array(CURLOPT_RETURNTRANSFER => true,
      CURLOPT_FOLLOWLOCATION => true,
      CURLOPT_MAXREDIRS => 5);
      $options = ($custom_options) ? ($std_options + $custom_options) : $std_options;
      $i=0;
      // Start the first batch of requests (Rolling Window)
      for ($i = 0; $i < $rolling_window; $i++) {
          // Initialize cURL handle
          $ch = curl_init();
          // Attach URL to this handle
          $options[CURLOPT_URL] = $notSeenLinks[$i];
          // Set cURL options for this handle
          curl_setopt_array($ch,$options);
          // Add this handle to the MASTER LIST
          curl_multi_add_handle($master, $ch);
      }


      // First do-while loop. Keep doing this while threads are still running. 
      // Basically the computer is going to check this loop anywhere from
      // 100,000 to a million+ times waiting to see if a thread has finished 
      // and wants to turn in the data it downloaded
      do {
          // Threads are running. Keep doing curl_multi_exec() until cURL sets the CURLM_OK variable
          while(($execrun = curl_multi_exec($master, $running)) == CURLM_CALL_MULTI_PERFORM);

          // If all threads have finished then break this 'First do-while loop'
          if($execrun != CURLM_OK)
              break;
          // Else keep checking to see if a thread finished.
          // A request was just completed -- find out which one
          while($done = curl_multi_info_read($master)) {

              // curl_getinfo() returns an array identifying the thread that has finished
              $info = curl_getinfo($done['handle']);
              // Check if this request was successful
              if ($info['http_code'] == 200)  {

                  // Get the data
                  $output = curl_multi_getcontent($done['handle']);
                  // Also, save data in $page_data
                  $page_data[$info['url']]=$output;                  
       
                  // This URL has now been seen, mark it
                  $this->seen[$info['url']]=true;                  

                  // This URL was downloaded, add it to the list
                  $this->downloadedURL[]=$info['url'];

                  // Also, increment the $pagesDownloaded counter
                     $this->pagesDownloaded++;

                  // Process output using the callback function.
                  $this->$callback($output, $info['url']);
                  flush();

                  // Start a new request (it's important to do this before removing the old one)
                  $ch = curl_init();
                  if (isset($notSeenLinks[($i+1)])){
                     $options[CURLOPT_URL] = $notSeenLinks[$i++];  // increment i                 
                     curl_setopt_array($ch,$options);
                     curl_multi_add_handle($master, $ch);
                  }

                  // Remove the curl handle that just completed
                  curl_multi_remove_handle($master, $done['handle']);
              } else {
                  // Request failed                    
              }
          }
      } while ($running); // Keep doing the "do" section while threads are still running.     

      curl_multi_close($master);


      foreach( $page_data as $key => $data){

                  // Now search this particular page for additional links
                  $domainLinks=$this->getDomainURLs($data, $key);                  
                  // Take the list of links found on this page and use it to 
                  // recursively download those pages
                  $this->rolling_curl($domainLinks, $callback, $custom_options = null);

      }

      return true;

} // END public function rolling_curl($urls, $callback, $custom_options = null) {


   // Parse page looking for keyword meta-data
   public function parseData($data, $url){    

   $pattern1='/<meta name="keywords" content="(.*)?" \/>/im';
   $pattern2='/<meta name="keywords" content="(.*)?">/im';

         // Search the page to find a match:
         if (preg_match($pattern1, $data, $match)) {
            // echo "<h4>Found keyword metadata for ".$url."</h4> "; 
           }       
         elseif (preg_match($pattern2, $data, $match)) {
            // echo "<h4>Found keyword metadata for ".$url."</h4> ";  
         }
         else{
            // echo "<h4>No meta-data was found for ".$url."</h4>";
          }


	$database= new Database("localhost", "crawler", "crawl123", "crawlerData");

      
         // If a match was found, get the keywords
         if(count($match)>1){     
            $pieces = explode(",", $match[1]);
            // Print keywords
            foreach ($pieces as $value){
               $trimmed=trim($value);
               // echo " Keyword: $trimmed <br />";               
               $results = $database->queryDB("call keywordsURLs('$trimmed','$url')");
            }
         } // CLOSE if(count($match)>1){   
 
        // Done with this database object, mark it to be destroyed
        unset($database); 
   } //CLOSE function parseData($PageData){



   // Scan downloaded page for URLs, then select those that are in the same domain 
   // as $url.
   public function getDomainURLs($page, $url){
   
   // This is a CRITICAL piece of code: find the domain name
   // in the URL of this page
   $domain = parse_url($this->domainToCrawl, PHP_URL_HOST); 
   $dom = new DOMDocument(); 
   $dom->strictErrorChecking = false; 
   // Load the downloaded page in DOM
   @$dom->loadHTML($page); 

   // Select all link elements in page source code
   $links = $dom->getElementsByTagName('a'); 
   
   // Store selected links here
   $domainLinks=array();
   foreach($links as $link) { 

      if ($link->hasAttribute('href')) { 
         $href = $link->getAttribute('href'); 
         // If website uses relative (\path\to\folder) vs. absolute (http:\\exm.com\index.html)
         // Then add the domain name to the relative paths.
         if(substr($href,0,1)=="/")
            $href=$this->domainToCrawl.$href;  
         // Use the domain name found above to see the links found
         // on the page belong to the same domain.
         $href_domain = parse_url($href, PHP_URL_HOST); 
         if ($href_domain == $domain) { 
            // The links found in the page belong to the same domain, so add them.
            $domainLinks[]=$href;            
         } 
      } 
   } 

   // Make sure there are no duplicates in this list
   $uniqueLinks=array_unique($domainLinks);

   return $uniqueLinks;

   }// CLOSE public function getURLlist(){  

} // CLOSE class DataSource





class Database
{

   // Store database server name/IP here
   public $server;

   // Store database username here
   public $user;

   // Store database username's password here
   public $pwd;

   // Store database name here
   public $database;

   // Store the database connection object here
   public $mysqli;



   // This is the default constructor
   function __construct($server, $user, $pwd, $database) {

      // Set Object variables
      $this->server=$server;
      $this->user=$user;
      $this->pwd=$pwd;
      $this->database=$database;
      
      // Connect to database
      $mysqli = new mysqli($this->server, $this->user, $this->pwd, $this->database);

      // Error checking
      if ($mysqli->connect_errno) {
         echo "Failed to connect to MySQL: (" . $mysqli->connect_errno . ") " . $mysqli->connect_error;
         // This is a fatal error. Exit
         exit ();
      }

      // Set database connection object
      $this->mysqli=&$mysqli;

   } // CLOSE function __construct($server, $user, $pwd, $database) {


   function __destruct() {

       // Close connection
       mysqli_close($this->mysqli);
   }




   public function queryDB($query){

      // Database connection
      $conn = &$this->mysqli;

      // ERROR CHECKING: Execute query and check for errors
      if (!$results=$conn->query($query)) {
         echo "Query Failed ( $query ) with error (" . $conn->errno . ") " . $conn->error;
      }
      // NO ERROR
      return $results;
   } // CLOSE public function connect (){





} // CLOSE class DataSource
$time_end = microtime(true);
$time = $time_end - $time_start;
echo "<h3>Total Time =".$time." Seconds</h3>";

?>
