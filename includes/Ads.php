<?php
/**
 * Ad Management System
 * Handles ad display logic for free vs premium users
 */

class Ads {
    private $enabled;
    private $provider;
    private $clientId;
    
    public function __construct() {
        require_once __DIR__ . '/EnvLoader.php';
        $this->enabled = EnvLoader::get('ENABLE_ADS', 'true') === 'true';
        $this->provider = EnvLoader::get('AD_PROVIDER', 'adsense');
        $this->clientId = EnvLoader::get('ADSENSE_CLIENT_ID', '');
    }
    
    /**
     * Check if ads should be shown for a user
     */
    public function shouldShowAds($userId) {
        if (!$this->enabled) {
            return false;
        }
        
        // If user is premium, don't show ads
        if ($userId) {
            require_once __DIR__ . '/Subscription.php';
            $subscription = new Subscription();
            if ($subscription->isPremium($userId)) {
                return false;
            }
        }
        
        return true;
    }
    
    /**
     * Get ad code for a specific placement
     */
    public function getAdCode($placement = 'banner') {
        if (!$this->shouldShowAds(null)) {
            return '';
        }
        
        switch ($this->provider) {
            case 'adsense':
                return $this->getAdSenseCode($placement);
            default:
                return '';
        }
    }
    
    /**
     * Get Google AdSense code
     */
    private function getAdSenseCode($placement) {
        if (empty($this->clientId)) {
            return '';
        }
        
        // Different ad sizes for different placements
        $adSlots = [
            'banner' => 'auto',
            'sidebar' => 'auto',
            'inline' => 'auto',
            'mobile' => 'auto'
        ];
        
        $slot = $adSlots[$placement] ?? 'auto';
        
        return "
        <div class='ad-container ad-{$placement}'>
            <ins class='adsbygoogle'
                 style='display:block'
                 data-ad-client='{$this->clientId}'
                 data-ad-slot='{$slot}'
                 data-ad-format='auto'
                 data-full-width-responsive='true'></ins>
            <script>
                 (adsbygoogle = window.adsbygoogle || []).push({});
            </script>
        </div>";
    }
    
    /**
     * Render ad container (client-side check for premium)
     */
    public function renderAdContainer($placement, $userId) {
        if (!$this->shouldShowAds($userId)) {
            return '';
        }
        
        $html = "<div class='ad-wrapper ad-{$placement}' data-user-id='" . ($userId ?? '') . "'>";
        $html .= $this->getAdCode($placement);
        $html .= "</div>";
        
        return $html;
    }
}

