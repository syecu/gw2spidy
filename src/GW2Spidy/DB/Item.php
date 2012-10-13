<?php

namespace GW2Spidy\DB;

use GW2Spidy\Application;

use GW2Spidy\Util\Functions;

use GW2Spidy\Util\ApplicationCache;
use GW2Spidy\DB\om\BaseItem;
use GW2Spidy\Util\CacheHandler;


/**
 * Skeleton subclass for representing a row from the 'item' table.
 *
 *
 *
 * You should add additional methods to this class to meet the
 * application requirements.  This class will only be generated as
 * long as it does not already exist in the output directory.
 *
 * @package    propel.generator.gw2spidy
 */
class Item extends BaseItem {
    const ONE_DAY = 86400;
    const ONE_HOUR = 3600;
    const THREE_HOURS = 10900;
    const FIFTEEN_MIN = 900;
    const FIVE_MIN = 300;

    const RARITY_COMMON     = 1;
    const RARITY_FINE       = 2;
    const RARITY_MASTERWORK = 3;
    const RARITY_RARE       = 4;
    const RARITY_EXOTIC     = 5;
    const RARITY_LEGENDARY  = 6;
    const FALSE_POSITIVE = 'FALSE_POSITIVE';

    public function preSave() {
        if ($this->isColumnModified(ItemPeer::MIN_SALE_UNIT_PRICE) || $this->isColumnModified(ItemPeer::MAX_OFFER_UNIT_PRICE)) {
            $this->setLastPriceChanged(new \DateTime());
        }

        return true;
    }

    public function getQueuePriority() {
        if ($this->getItemTypeId() === null) {
            return 24 * 60 * 60;
        }

        switch ($this->getItemType()->getTitle()) {
            case 'Weapon':
            case 'Armor':
                if ($this->getRarity() >= 3) {
                    if ($this->getRestrictionLevel() > 60) {
                        return self::FIFTEEN_MIN;
                    } else if ($this->getRestrictionLevel() > 40) {
                        return self::ONE_HOUR;
                    } else {
                        return self::THREE_HOURS;
                    }
                } else if ($this->getRarity() >= 2) {
                    return self::THREE_HOURS;
                } else {
                    return self::ONE_DAY;
                }

                break;

            case 'Gathering':
            case 'Tool':
                return self::ONE_DAY;

                break;

            case 'Trophy':
                if ($this->getRarity() >= 2) {
                    return self::FIFTEEN_MIN;
                } else {
                    return self::ONE_DAY;
                }

                break;

            case 'Gizmo':
                if ($this->getRarity() >= 5) {
                    return self::ONE_HOUR;
                } else {
                    return self::THREE_HOURS;
                }

                break;

            case 'Mini':
            case 'Bag':
            case 'Crafting Material':
                return self::FIFTEEN_MIN;

                break;

            case 'Container':
                if ($this->getRarity() >= 2) {
                    return self::FIFTEEN_MIN;
                } else {
                    return self::ONE_HOUR;
                }

                break;

            case 'Consumable':
            case 'Upgrade Component':
            case 'Trinket':
                return self::ONE_HOUR;

                break;

            default:
                throw new Exception("Unknown type {$this->getItemType()->getTitle()}");

                break;
        }
    }

    public function getRarityName() {
        switch ($this->getRarity()) {
            case self::RARITY_COMMON:     return "Common";
            case self::RARITY_FINE:       return "Fine";
            case self::RARITY_MASTERWORK: return "Masterwork";
            case self::RARITY_RARE:       return "Rare";
            case self::RARITY_EXOTIC:     return "Exotic";
            case self::RARITY_LEGENDARY:  return "Legendary";
            default:                      return "Rarity [{$this->getRarity()}]";
        }
    }

    public function getGW2DBTooltip($href = null) {
        $cache    = CacheHandler::getInstance('item_gw2db_tooltips');
        $cacheKey = $this->getDataId() . "::" . substr(md5($href),0,10);
        $ttl      = 86400;

        if (!($tooltip = $cache->get($cacheKey))) {

            $tooltip   = $this->getGW2DBTooltipFromGW2DB();
            if (!$tooltip) {
                $tooltip = self::FALSE_POSITIVE;
                $ttl     = 600;
            } else {
                $html      = str_get_html($tooltip);
                $gw2dbhref = Functions::getGW2DBLink($this);

                if ($href) {
                    $html->find('dt.db-title', 0)->innertext = <<<HTML
    <a href="{$href}">{$html->find('dt.db-title', 0)->innertext}</a>
HTML;
                }

                $html->find('div.db-description', 0)->style = "position: relative; z-index: 1;";
                $html->find('div.db-description', 0)->innertext .= <<<HTML
    <a href="{$gw2dbhref}" target="_blank" title="View this item on GW2DB" data-notooltip="true">
        <img src="/assets/img/powered_gw2db_onDark.png" width="80" style="position: absolute; bottom: 0px; right: 0px; opacity: 0.2;" />
    </a>
HTML;
                $tooltip = (string)$html;
            }

            $cache->set($cacheKey, $tooltip, MEMCACHE_COMPRESSED, $ttl);
        }

        return $tooltip == self::FALSE_POSITIVE ? null : $tooltip;
    }

    public function getGW2DBTooltipFromGW2DB() {
        $js = @file_get_contents("http://www.gw2db.com/items/{$this->getGW2DBExternalId()}/tooltip");

        if (!$js) {
            return null;
        }

        $js = preg_replace("/^(WP_OnTooltipLoaded)?\(/", '', $js);
        $js = preg_replace("/\)$/", '', $js);

        $data = json_decode($js, true);
        $html = $data['Tooltip'];

        return stripslashes($html);
    }

    public function getMargin() {
	$margin = intval($this->getMinSaleUnitPrice() * 0.85 - $this->getMaxOfferUnitPrice());
	if($this->getMaxOfferUnitPrice() == 0 | $this->getMinSaleUnitPrice() == 0)
	{
		$margin = 0;
	}

   	$margin = ($margin > 0) ? $margin : 0;
	return $margin;

    }

    public function getIngameCode() {

        $code = chr(2);
        $code .= chr(1);
        $code .= chr($this->getDataId() % 256);
        $code .= chr(floor($this->getDataId() / 256)) . chr(0) . chr(0);

        $code = base64_encode($code);

        return "[&{$code}]";
    }

   public function getRarityCSSClass() {
        return strtolower("rarity-" . str_replace(" ", "-", $this->getRarityName()));
    }

    /**
     * Get the associated ItemSubType object
     *
     * @param      PropelPDO   $con    Optional Connection object.
     * @return     ItemSubType         The associated ItemSubType object.
     * @throws     PropelException
     */
    public function getItemSubType(PropelPDO $con = null) {
        if ($this->aItemSubType === null && ($this->item_sub_type_id !== null)) {
            $cacheKey            = __CLASS__ . "::" . __METHOD__ . "::" . $this->getDataId();
            $this->aItemSubType  = ApplicationCache::getInstance()->get($cacheKey);

            if ($this->aItemSubType == static::FALSE_POSITIVE) {
                $this->aItemSubType = null;
            } else if (!$this->aItemSubType) {
                $this->aItemSubType = ItemSubTypeQuery::create()
                    ->filterByItem($this)
                    ->filterByMainTypeId($this->getItemTypeId())
                    ->findOne($con);

                ApplicationCache::getInstance()->set($cacheKey, $this->aItemSubType ?: static::FALSE_POSITIVE, MEMCACHE_COMPRESSED, 86400);
            }
        }

        return $this->aItemSubType;
    }

    public function __toString() {
        return $this->getName();
    }

} // Item
