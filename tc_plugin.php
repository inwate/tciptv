<?php
///////////////////////////////////////////////////////////////////////////

require_once 'lib/default_dune_plugin.php';
require_once 'lib/utils.php';

require_once 'lib/tv/tv_group_list_screen.php';
require_once 'lib/tv/tv_channel_list_screen.php';
require_once 'lib/tv/tv_favorites_screen.php';

require_once 'lib/vod/vod_list_screen.php';
require_once 'lib/vod/vod_movie_screen.php';
require_once 'lib/vod/vod_series_list_screen.php';
require_once 'lib/vod/vod_favorites_screen.php';

require_once 'tc_config.php';

require_once 'tc_tv.php';
require_once 'tc_vod.php';
require_once 'tc_setup_screen.php';
require_once 'tc_vod_category_list_screen.php';
require_once 'tc_vod_list_screen.php';

///////////////////////////////////////////////////////////////////////////

class TeleconnectPlugin extends DefaultDunePlugin
{
    public function __construct()
    {
        $this->tv = new TeleconnectTv();
        $this->vod = new TeleconnectVod();

        $tv_folder_views = $this->get_tv_folder_views();

        $this->add_screen(new TvGroupListScreen($this->tv,
                TeleconnectConfig::GET_TV_GROUP_LIST_FOLDER_VIEWS()));
        $this->add_screen(new TvChannelListScreen($this->tv,
                TeleconnectConfig::GET_TV_CHANNEL_LIST_FOLDER_VIEWS()));
        $this->add_screen(new TvFavoritesScreen($this->tv,
                TeleconnectConfig::GET_TV_CHANNEL_LIST_FOLDER_VIEWS()));

        $this->add_screen(new TeleconnectSetupScreen());
        $this->add_screen(new VodFavoritesScreen($this->vod));
        $this->add_screen(new TeleconnectVodCategoryListScreen());
        $this->add_screen(new TeleconnectVodListScreen($this->vod));
        $this->add_screen(new VodMovieScreen($this->vod));
        $this->add_screen(new VodSeriesListScreen($this->vod));
    }

    ///////////////////////////////////////////////////////////////////////

    private function get_tv_folder_views()
    {
        return TeleconnectConfig::GET_TV_CHANNEL_LIST_FOLDER_VIEWS();
    }
}

///////////////////////////////////////////////////////////////////////////
?>
