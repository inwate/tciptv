<?php
///////////////////////////////////////////////////////////////////////////

require_once 'lib/hashed_array.php';
require_once 'lib/tv/abstract_tv.php';
require_once 'lib/tv/default_epg_item.php';

require_once 'tc_channel.php';


///////////////////////////////////////////////////////////////////////////

class DemoTv extends AbstractTv
{
    private $chid2num;
//    private $pstc;
//    private $psshift;
    private $parsed_schedule = null;
    private $ndx_map;
    private $pdt_map;
    private $schedule_age = 0;    

    const VLC = "http://www.videolan.org/vlc/playlist/ns/0/";

    public function __construct()
    {
        parent::__construct(
            AbstractTv::MODE_CHANNELS_N_TO_M,
            DemoConfig::TV_FAVORITES_SUPPORTED,
            true);

    }

    public function get_fav_icon_url()
    {
        return DemoConfig::FAV_CHANNEL_GROUP_ICON_PATH;
    }

    ///////////////////////////////////////////////////////////////////////
    ///////////////////////////////////////////////////////////////////////

    protected function load_channels(&$plugin_cookies)
    {

        date_default_timezone_set('Etc/GMT+7');

        $doc = HD::http_get_document(DemoConfig::CHANNEL_LIST_URL);
     
        if (is_null($doc))
            throw new Exception('Can not fetch playlist');

        $xml =  simplexml_load_string($doc);


        if ($xml === false)
        {
            hd_print("Error: can not parse XML document.");
            hd_print("XML-text: $doc.");
            throw new Exception('Illegal XML document');
        }

        if ($xml->getName() !== 'playlist')
        {
            hd_print("Error: unexpected node '" . $xml->getName() . "'. Expected: 'playlist'");
            throw new Exception('Invalid XML document');
        }


        $this->channels = new HashedArray();
        $this->groups = new HashedArray();
        $this->chid2num = array();
        $this->pstc = array();
		$this->psshift = array();

        if ($this->is_favorites_supported())
        {
            $this->groups->put(
                new FavoritesGroup(
                    $this,
                    '__favorites',
                    DemoConfig::FAV_CHANNEL_GROUP_CAPTION,
                    DemoConfig::FAV_CHANNEL_GROUP_ICON_PATH));
        }


        $this->groups->put(
            new AllChannelsGroup(
                $this,
                DemoConfig::ALL_CHANNEL_GROUP_CAPTION,
                DemoConfig::ALL_CHANNEL_GROUP_ICON_PATH));
        

        //parse channels
		$id = 0;
        foreach ($xml->trackList->children() as $xml_tv_channel)
        {
            if ($xml_tv_channel->getName() !== 'track')
            {
                hd_print("Error: unexpected node '" . $xml_tv_channel->getName() .
                    "'. Expected: 'track'");
                throw new Exception('Invalid XML document');
            }

			
            $number = intval($xml_tv_channel->extension->children(self::VLC));

			//does channel have ps?
			$psname=null;
			$psshift=null;
            if (isset($xml_tv_channel->psfile))
            {
        		$psname=(string)$xml_tv_channel->psfile;
    			if(isset($xml_tv_channel->shift))
    		    	$psshift=intval($xml_tv_channel->shift)*60;
    			else
    		    	$psshift=0;
			}

            $channel =
                new IPTVChannel(
                    strval($id),
                    strval($xml_tv_channel->title),
                    strval($xml_tv_channel->image),
                    strval($xml_tv_channel->location),
                    strval($number),
                    intval(2),
                    intval(2),
					$psname,
					$psshift);

            $this->channels->put($channel);
			$id++;
        }

        //parse groups

        $group_num = 1;

        foreach ($xml->extension->children(self::VLC) as $xml_tv_category)
        {

            $group_title = strval($xml_tv_category->attributes()->title);
            $group_icon = null;

            switch ($group_title) {

                case "Познавательные" :                   	$group_icon = "plugin_file://icons/grp_edu.png"; break;
                case "Бизнес" :                           	$group_icon = "plugin_file://icons/grp_news.png"; break;
                case "Эфир" :                               $group_icon = "plugin_file://icons/grp_air.png"; break;
                case "Детям и мамам" :                      $group_icon = "plugin_file://icons/grp_kids.png"; break;
                case "Кино и сериалы" :                     $group_icon = "plugin_file://icons/grp_cinema.png"; break;
                case "Развлекательные" :         			$group_icon = "plugin_file://icons/grp_hobby.png"; break;
                case "Музыкальные" :                 		$group_icon = "plugin_file://icons/grp_music.png"; break;
                case "Спорт" :                             	$group_icon = "plugin_file://icons/grp_sport.png"; break;
            }

            $group = new DefaultGroup(strval($group_num), $group_title, $group_icon);

            $this->groups->put($group);

            $group_num++;

            foreach($xml_tv_category->children(self::VLC) as $cat_item)
            {
                $number = intval($cat_item->attributes()->tid);
				//  $channel = $this->channels->get($ch_id);
				if ($this->getChannelByNumber($number)!=NULL)
				$channel->add_group($group);

                $group->add_channel($channel);
                    
            }
        }
    }

    ///////////////////////////////////////////////////////////////////////////
    ///////////////////////////////////////////////////////////////////////////

    public function get_day_epg_iterator($channel_id, $day_start_ts, &$plugin_cookies) {
        if (time() - $this->schedule_age > 3600) {
            $this->parsed_schedule = null;
        }

        if (is_null($this->parsed_schedule)) {
            $ts1 = microtime(true);

            hd_print("first time load/reload schedule");

            $this->parsed_schedule = array();

            file_put_contents("/tmp/schedule.zip", file_get_contents(DemoConfig::EPG_URL));
            $zip = zip_open("/tmp/schedule.zip");

            while(($zip_entry = zip_read($zip)) !== false) {
                $entry_name = zip_entry_name($zip_entry);
                $entry_ext = substr($entry_name, strrpos($entry_name, '.') + 1);
                $entry_partname = iconv('CP866','UTF-8',substr($entry_name, 0, strrpos($entry_name, '.')));
    
                if ($entry_ext == "ndx") {
                    $this->ndx_map[$entry_partname] = zip_entry_read($zip_entry, zip_entry_filesize($zip_entry));
                } else if ($entry_ext == "pdt") {
                    $this->pdt_map[$entry_partname] = zip_entry_read($zip_entry, zip_entry_filesize($zip_entry));
                } else {
                    hd_print("unexpected ext ".$entry_ext);
                }

            }

            $this->schedule_age = time();

            $ts2 = microtime(true);
            hd_print("schedule downloaded for ".($ts2-$ts1));
        }

	$current = $channel->get($channel_id);
	$num = $current->get_psname();
	$regshift=0;
	if ($num!=NULL)
	{
		 $regshift = $current->get_psshift();
		 if ($regshift==NULL)
		 	$regshift=0;	
	}
/*
	$num = $this->pstc[$channel_id];
	if (isset($this->psshift[$channel_id]))
	    $regshift = $this->psshift[$channel_id];
	else
	    $regshift=0;
        //$num = $this->chid2num[$channel_id];
*/
        $epg = array();

        if (array_key_exists($num, $this->ndx_map)) {
            if (!array_key_exists($num, $this->parsed_schedule)) {
                $ts3 = microtime(true);

                $ndx_data = $this->ndx_map[$num];
                $parsed_ndx = unpack("v", substr($ndx_data, 0, 2));
                $prog_count = $parsed_ndx[1];
                $pdt_data = $this->pdt_map[$num];
                $ch_schedule = array();

                for ($i = 0; $i < $prog_count; $i++) {
                    $parsed_ndx = unpack("x2/V2time/voffset/", substr($ndx_data, 2 + $i * 12, 12));  //time1, time2, offset
                    $time1 = $parsed_ndx["time1"];
                    $time2 = $parsed_ndx["time2"];

                    $time_unix = ($time2 - 27111902.832985)  * 429.4967296 + ($time1 / 10000000.0) + ($time1 < 0 ? 429.4967296 : 0);
                    $time_unix = round($time_unix / 10) * 10;
                    $time_unix = $time_unix - 7 * 3600; //NSK GMT offset
                    $time_unix = $time_unix + $regshift;
        
                    $offset = $parsed_ndx["offset"];
                    $title_length_arr = unpack("v", substr($pdt_data, $offset, 2));
                    $title = substr($pdt_data, $offset + 2, $title_length_arr[1]);

                    $title_utf = iconv("CP1251", "UTF-8", $title);

                    $ch_schedule[] = array(intval($time_unix), $title_utf);

                }

                //fill epg
                for ($i = 0; $i < count($ch_schedule); $i++) {
                    $start_time = $ch_schedule[$i][0];

                    if ($i < count($ch_schedule) - 1) {
                        $stop_time = $ch_schedule[$i + 1][0];
                    } else {
                        $stop_time = $start_time + 3600;
                    }

                    $epg[] = new DefaultEpgItem($ch_schedule[$i][1], "", $start_time, $stop_time);
                }

                $this->parsed_schedule[$num] = $epg;

                $ts4 = microtime(true);
                //hd_print("schedule parsed for ".($ts4-$ts3));
            }

            $epg = $this->parsed_schedule[$num];
        }

        $filtered_epg = array();

        foreach($epg as $epg_item) {
            if ($epg_item->get_start_time() >= $day_start_ts && $epg_item->get_start_time() <= $day_start_ts + 86400) {
                $filtered_epg[] = $epg_item;
            }
        }

        return new EpgIterator($filtered_epg, $day_start_ts, $day_start_ts + 86400);
    }

	private function getChannelByNumber($number)
	{
		$this->channels->rewind();
		while($this->channels->valid())
		{
			$channel = $this->channels->current();
			if($channel->get_number()==$number)
				return $channel;
			$this->channels->next();
		}
		return null;
	}

}

///////////////////////////////////////////////////////////////////////////
?>
