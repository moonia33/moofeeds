<?php
if (!defined('_PS_VERSION_')) { exit; }

class moofeedsgoogleadsModuleFrontController extends ModuleFrontController
{
    private function cacheBase(){ return _PS_MODULE_DIR_ . 'moofeeds/var/cache/'; }
    private function tryServeCache($langId,$shopId,$currencyIso){ $base=$this->cacheBase(); $file=sprintf('%s%s-%d-%d-%s.csv',$base,'googleads',$shopId,$langId,$currencyIso); if(is_file($file)){ header('Content-Type: text/csv; charset=UTF-8'); header('X-Content-Type-Options: nosniff'); $mtime=filemtime($file); header('Last-Modified: '.gmdate('D, d M Y H:i:s',$mtime).' GMT'); $etag='W/"'.md5($file.$mtime.filesize($file)).'"'; header('ETag: '.$etag); header('Cache-Control: public, max-age=21600'); if((isset($_SERVER['HTTP_IF_NONE_MATCH']) && trim($_SERVER['HTTP_IF_NONE_MATCH'])===$etag)){ http_response_code(304); exit; } readfile($file); exit; } }
    public function initContent(){ parent::initContent(); $langId=(int)$this->context->language->id; $currency=$this->context->currency; $currencyIso=is_object($currency)&&isset($currency->iso_code)?$currency->iso_code:(is_string($currency)?$currency:'EUR'); $shopId=(int)$this->context->shop->id; $this->tryServeCache($langId,$shopId,$currencyIso); header('Content-Type: text/csv; charset=UTF-8'); header('X-Content-Type-Options: nosniff'); header('Cache-Control: no-cache, no-store, must-revalidate'); $out=fopen('php://output','w');
        // Output only the header when cache is cold, to indicate expected schema
        fputcsv($out,[
            'ID','ID2','Item title','Final URL','Image URL','Item subtitle','Item description','Item category','Price','Sale price','Contextual keywords','Item address','Tracking template','Custom parameter','Final mobile URL','Android app link','iOS app link','iOS app store ID','Formatted price','Formatted sale price'
        ]);
        fclose($out); exit; }
}
