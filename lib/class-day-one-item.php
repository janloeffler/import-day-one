<?php
// Class for DayOne data item
class DayOneItem {
    private $date;
    public $tags;
    public $location;
    public $geo_latitude;
    public $geo_longitude;
    public $weather;
    public $photo;
    public $starred;
    public $text;
    public $exists = false;
    public $isRoughDate = false;

    public function matchDate( $date_to_compare ) {
        $d1 = $this->date;
        $d2 = $this->convertDate( $date_to_compare );
        return ( strcmp( $d1, $d2 ) == 0 );
    }

    public function cmpDate( $date_to_compare, $length ) {
        $d1 = substr( $this->date, 0, $length );
        $d2 = substr( $this->convertDate( $date_to_compare ), 0, $length );
        return ( strcmp( $d1, $d2 ) == 0 );
    }
    
    // "2014-03-08 13:42:00" -> "2014-03-08 13"
    public function matchRoughDate( $date_to_compare ) {
        return cmpDate( $date_to_compare, 13 );
    }

    // "2014-03-08 13:42:00" -> "2014-03-08"
    public function sameDay( $date_to_compare ) {
        return cmpDate( $date_to_compare, 10 );
    }
    
    public function hasValues() {
        return $this->location || $this->weather;
    }

    public function getTags() {
        $tags = $this->tags;

        if ( $this->starred && !( strpos( $tags, "Highlight" ) !== false ) ) {
            if ( $tags ) {
                $tags .= ", Highlight";
            } else {
                $tags = "Highlight";
            }
        }

        return $tags;
    }

    public function getDate() {
        return $this->date;
    }

    public function setDate( $d ) {
        $this->date = $this->convertDate( $d );
        
        return $this->date;
     }
    
    public function convertDate( $date ) {
        if ( strpos( $date, '-' ) === false ) {
            $d = $this->fixMonth( $date );
            try {
                $post_date = date_create_from_format( 'j F Y H:i', $d );
                if ( $post_date ) {
                    return date( 'Y-m-d H:i:s', strtotime( date_format( $post_date, 'Y-m-d H:i:s' ) ) );
                }
            }
            catch ( Exception $e ) {
                echo 'Exception at convertDate(' . $d . '): ', $e->getMessage(), "<br />\n";
            }

            return date( 'Y-m-d H:i:s', strtotime( $date ) );
        }
        
        return $date;
    }
    
    private function fixMonth( $date ) {
        $from  = array(
            "Januar",
            "Februar",
            "MŠrz",
            "Mai",
            "Juni",
            "Juli",
            "Oktober",
            "Dezember",
            "Jan ",
            "Feb ",
            "Mar ",
            "Apr ",
            "Jun ",
            "Jul ",
            "Aug ",
            "Sep ",
            "Oct ",
            "Nov ",
            "Dec ",
            "."
        );

        $to = array(
            "January",
            "February",
            "March",
            "May",
            "June",
            "July",
            "October",
            "December",
            "January ",
            "February ",
            "March ",
            "April ",
            "June ",
            "July ",
            "August ",
            "September ",
            "October ",
            "November ",
            "December ",
            ""
        );
        
        // Schei§ Umlaute!!!
        $date = preg_replace('/M.*?rz/','March' , $date);
        return str_ireplace( $from, $to, $date );
    }

    public function findGeoLocation() {
        $location = $this->location;
        if ( $location ) {
            $location    = urlencode( $location );
            $request_url = "http://maps.googleapis.com/maps/api/geocode/xml?address=" . $location . "&sensor=true";
            try {
                $xml = simplexml_load_file( $request_url ) or die( "url not loading" );
                $status = $xml->status;
                if ( $status == "OK" ) {
                    $this->geo_latitude  = $xml->result->geometry->location->lat;
                    $this->geo_longitude = $xml->result->geometry->location->lng;
                }
            }
            catch ( Exception $e ) {
                echo '<!--Exception at find geo location at DayOne entry ' . $this->toString() . ': ', $e->getMessage(), "-->\n";
            }
        }
    }

    public function toString() {
        return 'Date: ' . $this->date . ', Tags: ' . $this->getTags() . ', Location: ' . $this->location . ', Weather: ' . $this->weather . ', Photo: ' . $this->photo . ', Text: ' . $this->text;
    }
    
    public function toShortString() {
        return 'Date: ' . $this->date . ', Text: ' . substr( $this->text, 0, 20 ) . '[...]';
    }
}
?>