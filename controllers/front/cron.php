<?php
if (!defined('_PS_VERSION_')) { exit; }

class moofeedscronModuleFrontController extends ModuleFrontController
{
    private function ensureDir($path){ if (!is_dir($path)) { @mkdir($path, 0775, true); } }
    private function cacheBase(){ return _PS_MODULE_DIR_ . 'moofeeds/var/cache/'; }
    private function lockBase(){ return _PS_MODULE_DIR_ . 'moofeeds/var/lock/'; }
    private function normalizeSentenceCase($text){ $s=trim((string)$text); if($s===''){return $s;} $s=Tools::strtolower($s); return preg_replace_callback('/(^|[\.!\?]\s+|\s-\s)(\p{L})/u',function($m){return $m[1].mb_strtoupper($m[2],'UTF-8');},$s);}    
    private function extractFeatureValue($features,$candidateNames){ if(!is_array($features)||!is_array($candidateNames)){return '';} foreach($features as $f){ $name=Tools::strtolower(trim((string)($f['name']??''))); $val=trim((string)($f['value']??'')); if($name===''){continue;} foreach($candidateNames as $cand){ if($name===Tools::strtolower($cand)){ return $val; } } } return ''; }
    private function deriveGender($features,$categoryName,$default='female'){ $v=$this->extractFeatureValue($features,['lytis','gender']); $srcs=[$v,(string)$categoryName]; foreach($srcs as $src){ $s=Tools::strtolower($src); if($s===''){continue;} if(strpos($s,'vyr')!==false){return 'male';} if(strpos($s,'mot')!==false){return 'female';} if(strpos($s,'uni')!==false||strpos($s,'abi')!==false){return 'unisex';} } return $default; }
    private function sanitizeInternalLabel($s){ $s=Tools::strtolower(trim((string)$s)); $s=preg_replace('/\s+/u','-',$s); $s=preg_replace("/[^a-z0-9_-]+/u",'', $s); if(mb_strlen($s,'UTF-8')>110){ $s=mb_substr($s,0,110,'UTF-8'); } return $s; }
    private function isProductNew(Product $product){ $days=(int)Configuration::get('PS_NB_DAYS_NEW_PRODUCT'); if($days<=0){ $days=20; } $dateAdd=isset($product->date_add)?strtotime($product->date_add):0; if($dateAdd<=0){ return false; } return $dateAdd >= (time() - $days*86400); }
    private function isProductTop($productId){ if(class_exists('ProductSale')){ if(method_exists('ProductSale','getNbSales')){ try { $n=(int)ProductSale::getNbSales((int)$productId); return $n>=10; } catch(\Throwable $e){ return false; } } } return false; }
    private function buildPaths($feed,$shopId,$langId,$currencyIso){ $base=$this->cacheBase(); $this->ensureDir($base); $tmp=sprintf('%s%s-%d-%d-%s.csv.tmp',$base,$feed,$shopId,$langId,$currencyIso); $fin=sprintf('%s%s-%d-%d-%s.csv',$base,$feed,$shopId,$langId,$currencyIso); $state=sprintf('%s%s-%d-%d-%s.state.json',$base,$feed,$shopId,$langId,$currencyIso); return [$tmp,$fin,$state]; }
    private function buildLock($feed,$shopId,$langId,$currencyIso){ $base=$this->lockBase(); $this->ensureDir($base); return sprintf('%s%s-%d-%d-%s.lock',$base,$feed,$shopId,$langId,$currencyIso); }
    private function openLock($lockPath){ $fh=fopen($lockPath,'c'); if($fh&&flock($fh,LOCK_EX|LOCK_NB)){ return $fh; } return null; }
    private function getTaxRateForDefaultCountry(Product $product){ $countryId=(int)Configuration::get('PS_COUNTRY_DEFAULT'); $address=new Address(); $address->id_country=$countryId; $address->id_state=0; $address->postcode=''; $rate=0.0; if((int)$product->id_tax_rules_group>0){ $tm=TaxManagerFactory::getManager($address,(int)$product->id_tax_rules_group); $calc=$tm->getTaxCalculator(); $rate=(float)$calc->getTotalRate(); } return $rate; }

    public function initContent()
    {
        parent::initContent();
        $token=Tools::getValue('token'); $remote=Tools::getRemoteAddr(); $cfgToken=(string)Configuration::get('MOOFEEDS_CRON_TOKEN');
        if(!in_array($remote,['127.0.0.1','::1']) && ($cfgToken===''||$token!==$cfgToken)){ header('Content-Type: application/json'); http_response_code(403); echo json_encode(['status'=>'forbidden']); exit; }

        $feed=Tools::getValue('feed'); if(!in_array($feed,['facebook','googleads','newsman'])){ header('Content-Type: application/json'); http_response_code(400); echo json_encode(['status'=>'invalid feed']); exit; }

        $langId=(int)$this->context->language->id; $shopId=(int)$this->context->shop->id; $currency=$this->context->currency; $currencyIso=is_object($currency)&&isset($currency->iso_code)?$currency->iso_code:(is_string($currency)?$currency:'EUR');
        list($tmpFile,$finalFile,$stateFile)=$this->buildPaths($feed,$shopId,$langId,$currencyIso); $lockFile=$this->buildLock($feed,$shopId,$langId,$currencyIso); $lock=$this->openLock($lockFile); if(!$lock){ header('Content-Type: application/json'); echo json_encode(['status'=>'busy']); exit; }

        $defSize=(int)Configuration::get('MOOFEEDS_DEF_SIZE'); $defSteps=(int)Configuration::get('MOOFEEDS_DEF_STEPS'); if($defSize<1){$defSize=1000;} if($defSteps<1){$defSteps=3;} $size=max(1,(int)Tools::getValue('size',$defSize)); $maxSteps=max(1,(int)Tools::getValue('max_steps',$defSteps)); $reset=(bool)Tools::getValue('reset',false);

        $state=['last_id'=>0,'total'=>null,'processed'=>0];
        if(file_exists($stateFile)&&!$reset){ $json=@file_get_contents($stateFile); if($json){ $s=json_decode($json,true); if(is_array($s)){ $state=array_merge($state,$s); } } }
        else if($reset||!file_exists($finalFile)){
            $out=fopen($tmpFile,'w');
            if($feed==='facebook'){ fputcsv($out,['id','title','description','availability','condition','price','link','image_link','brand','sale_price','item_group_id','google_product_category','product_type','mpn','gtin','age_group','gender','color','material','internal_label']); }
            elseif($feed==='googleads'){
                // Google Ads Business Data feed requires exact header names (case- and space-sensitive)
                // See: https://support.google.com/google-ads/answer/6053288
                fputcsv($out,[
                    'ID','ID2','Item title','Final URL','Image URL','Item subtitle','Item description','Item category','Price','Sale price','Contextual keywords','Item address','Tracking template','Custom parameter','Final mobile URL','Android app link','iOS app link','iOS app store ID','Formatted price','Formatted sale price'
                ]);
            }
            else { fputcsv($out,['product id','product name','product url','image url','stock','price','sale price','brand','category','description']); }
            fclose($out); @chmod($tmpFile,0664); @unlink($finalFile); $state['last_id']=0; $state['processed']=0;
        } else { fclose($lock); header('Content-Type: application/json'); echo json_encode(['status'=>'done','file'=>$finalFile]); exit; }

        $step=0; $db=Db::getInstance(); $link=$this->context->link;
        while($step<$maxSteps){
            $sql=new DbQuery();
            $sql->select('p.id_product, pl.name, pl.link_rewrite, p.id_manufacturer, p.id_category_default, sa.quantity')
                ->from('product','p')
                ->innerJoin('product_lang','pl','pl.id_product = p.id_product AND pl.id_lang='.(int)$langId)
                ->leftJoin('stock_available','sa','sa.id_product = p.id_product AND sa.id_product_attribute = 0')
                ->where('p.active = 1')
                ->where('sa.quantity > 0')
                ->where('p.id_product > '.(int)$state['last_id'])
                ->orderBy('p.id_product ASC')
                ->limit($size);
            $rows=$db->executeS($sql);
            if(!$rows||count($rows)===0){ $renOk=@rename($tmpFile,$finalFile); if(!$renOk){ if(@copy($tmpFile,$finalFile)){ @unlink($tmpFile); $renOk=true; } } if($renOk){ @chmod($finalFile,0664);} $fsize=is_file($finalFile)?(int)@filesize($finalFile):0; @unlink($stateFile); fclose($lock); @unlink($lockFile); header('Content-Type: application/json'); echo json_encode(['status'=>'done','file'=>$finalFile,'size'=>$fsize,'renamed'=>(bool)$renOk]); exit; }

            $out=fopen($tmpFile,'a');
            foreach($rows as $row){
                $productId=(int)$row['id_product']; $state['last_id']=$productId; $prodObj=new Product($productId,false,$langId);
                $url=$link->getProductLink($productId); $cover=Product::getCover($productId); $imageUrl=''; if(!empty($cover['id_image'])){ $imageUrl=$link->getImageLink($row['link_rewrite'],$cover['id_image'],'large_default'); if(strpos($imageUrl,'//')===0){ $imageUrl='https:'.$imageUrl; } }
                $taxRate=$this->getTaxRateForDefaultCountry($prodObj); $currentExcl=(float)Product::getPriceStatic((int)$productId,false,null,2,null,false,true);
                $spo=null; $baseExcl=(float)Product::getPriceStatic((int)$productId,false,null,2,null,false,false,1,false,null,null,null,$spo,true,false,null,false);
                $priceWithTax=$currentExcl*(1+$taxRate/100.0); $basePriceWithTax=$baseExcl*(1+$taxRate/100.0);
                $currencyIsoLocal=$currencyIso; $priceStr=number_format($basePriceWithTax,2,'.','').' '.$currencyIsoLocal; $saleStr=''; if($priceWithTax>0 && $priceWithTax<($basePriceWithTax-0.005)){ $saleStr=number_format($priceWithTax,2,'.','').' '.$currencyIsoLocal; }
                $availability=((int)$row['quantity']>0)?'in stock':'out of stock'; $condition=!empty($prodObj->condition)?$prodObj->condition:'new';
                $brand=''; if(!empty($row['id_manufacturer'])){ $man=new Manufacturer((int)$row['id_manufacturer'],$langId); $brand=(string)$man->name; }
                $categoryName=''; $maleByCatTree=false; if(!empty($row['id_category_default'])){ $cat=new Category((int)$row['id_category_default'],$langId); $categoryName=(string)$cat->name; if(Validate::isLoadedObject($cat) && method_exists($cat,'getParentsCategories')){ $parents=$cat->getParentsCategories($langId); if(is_array($parents)){ foreach($parents as $pc){ if((int)($pc['id_category']??0)===46){ $maleByCatTree=true; break; } } } } }
                $features=$prodObj->getFrontFeatures($langId); $labels=['','','','','']; $idx=0; foreach($features as $f){ if($idx>4){break;} $labels[$idx]=$this->normalizeSentenceCase(trim($f['value'])); $idx++; }
                $mpn=$prodObj->mpn?:$prodObj->reference; $gtin=$prodObj->ean13?:$prodObj->upc;
                if($feed==='facebook'){
                    $descParts=[]; if($categoryName){$descParts[]=$categoryName;} if(!empty($row['name'])){$descParts[]=$row['name'];} if($brand){$descParts[]=$brand;} $segA=[]; foreach($descParts as $dp){ $segA[]=$this->normalizeSentenceCase($dp);} $description=implode(' - ',$segA); $productType=$this->normalizeSentenceCase($categoryName); $ageGroup='adult'; $gender=$this->deriveGender($features,$categoryName,'female'); if($maleByCatTree){$gender='male';} $color=$this->extractFeatureValue($features,['spalva']); $material=$this->extractFeatureValue($features,['dominuoja']);
                    // Build internal_label list: use up to 5 feature values + dynamic flags
                    $intLabels=[]; foreach($labels as $lv){ $lv=$this->sanitizeInternalLabel($lv); if($lv!==''){ $intLabels[]=$lv; } }
                    if($this->isProductNew($prodObj)){ $intLabels[]='new'; }
                    if($this->isProductTop($productId)){ $intLabels[]='top'; }
                    // Deduplicate and cap reasonable length
                    $intLabels=array_values(array_unique(array_filter($intLabels,function($v){return $v!=='';})));
                    $intStr='['.implode(',',array_map(function($v){ return "'".$v."'"; },$intLabels)).']';
                    fputcsv($out,[$productId,$this->normalizeSentenceCase($row['name']),$description,$availability,$condition,$priceStr,$url,$imageUrl,$this->normalizeSentenceCase($brand),$saleStr,'',$this->normalizeSentenceCase($categoryName),$productType,(string)$mpn,(string)$gtin,$ageGroup,$gender,$this->normalizeSentenceCase($color),$this->normalizeSentenceCase($material),$intStr]);
                } elseif($feed==='googleads'){
                    // Build optional description similar to FB feed
                    $descParts=[]; if($categoryName){$descParts[]=$categoryName;} if(!empty($row['name'])){$descParts[]=$row['name'];} if($brand){$descParts[]=$brand;}
                    $segA=[]; foreach($descParts as $dp){ $segA[]=$this->normalizeSentenceCase($dp);} $description=implode(' - ',$segA);
                    // Contextual keywords: use up to 5 feature values and brand/category keywords
                    $kwParts=[]; foreach($labels as $kv){ if($kv!==''){ $kwParts[]=$kv; } }
                    if($brand!==''){ $kwParts[]=$this->normalizeSentenceCase($brand); }
                    if($categoryName!==''){ $kwParts[]=$this->normalizeSentenceCase($categoryName); }
                    $contextualKeywords=implode('; ',array_slice($kwParts,0,8));
                    // Align to the official Google Ads header order
                    fputcsv($out,[
                        $productId,                  // ID
                        '',                           // ID2
                        $this->normalizeSentenceCase($row['name']), // Item title
                        $url,                         // Final URL
                        $imageUrl,                    // Image URL
                        '',                           // Item subtitle
                        $description,                 // Item description
                        $this->normalizeSentenceCase($categoryName), // Item category
                        $priceStr,                    // Price
                        $saleStr,                     // Sale price
                        $contextualKeywords,          // Contextual keywords
                        '',                           // Item address
                        '',                           // Tracking template
                        '',                           // Custom parameter
                        $url,                         // Final mobile URL (fallback to Final URL)
                        '',                           // Android app link
                        '',                           // iOS app link
                        '',                           // iOS app store ID
                        '',                           // Formatted price
                        ''                            // Formatted sale price
                    ]);
                } else {
                    $priceNum=(float)number_format($basePriceWithTax,2,'.',''); $saleNum=($priceWithTax>0 && $priceWithTax<($basePriceWithTax-0.005))?(float)number_format($priceWithTax,2,'.',''):$priceNum; $descParts=[]; if($categoryName){$descParts[]=$categoryName;} if(!empty($row['name'])){$descParts[]=$row['name'];} if($brand){$descParts[]=$brand;} $segA=[]; foreach($descParts as $dp){ $segA[]=$this->normalizeSentenceCase($dp);} $description=implode(' - ',$segA);
                    fputcsv($out,[$productId,$this->normalizeSentenceCase($row['name']),$url,$imageUrl,(int)$row['quantity'],number_format($priceNum,2,'.',''),number_format($saleNum,2,'.',''),$this->normalizeSentenceCase($brand),$this->normalizeSentenceCase($categoryName),$description]);
                }
                $state['processed']++;
            }
            fclose($out);
            file_put_contents($stateFile,json_encode($state+['ts'=>time()]));
            $step++;
        }
        fclose($lock); header('Content-Type: application/json'); echo json_encode(['status'=>'progress','state'=>$state]); exit;
    }
}
