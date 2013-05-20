<?php
///////////////////////////////////////////////////////////////////////////

require_once 'lib/tv/default_channel.php';

///////////////////////////////////////////////////////////////////////////

class IPTVChannel extends DefaultChannel
{
    private $number;
    private $past_epg_days;
    private $future_epg_days;
	private $psname; 			//PS name
	private $psshift;			//PS shift

    ///////////////////////////////////////////////////////////////////////

    public function __construct(
        $id, $title, $icon_url, $streaming_url, $number, $past_epg_days, $future_epg_days)
    {

        parent::__construct($id, $title, $icon_url, $streaming_url);

        $this->number = $number;
        $this->past_epg_days = $past_epg_days;
        $this->future_epg_days = $future_epg_days;
		$psname=null;
		$psshift=null;
    }
	
	public function __construct(
        $id, $title, $icon_url, $streaming_url, $number, $past_epg_days, $future_epg_days,$psname,$psshift)
    {

        parent::__construct($id, $title, $icon_url, $streaming_url);

        $this->number = $number;
        $this->past_epg_days = $past_epg_days;
        $this->future_epg_days = $future_epg_days;
		$this->psname;
		$this->psshift;

    }

    ///////////////////////////////////////////////////////////////////////

    public function get_number()
    { return $this->number; }

    public function get_past_epg_days()
    { return $this->past_epg_days; }

    public function get_future_epg_days()
    { return $this->future_epg_days; }
	
	public function get_psname()
	{
		return $this->psname;
	}
	
	public function get_psshift()
	{
		return $this->psshift;
	}
}

///////////////////////////////////////////////////////////////////////////
?>
