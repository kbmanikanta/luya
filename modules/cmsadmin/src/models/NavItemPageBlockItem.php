<?php

namespace cmsadmin\models;

use Yii;
use cmsadmin\Module;

/**
 * Represents an ITEM for the type NavItemPage.
 * 
 * Sort_index numbers always starts from 0 and not from 1, like a default array behaviour. If a
 * negative sort_index is provided its always the last sort_index item (reason: we dont know the sort key of
 * the "at the end" dropparea).
 *
 * @property integer $id
 * @property integer $block_id
 * @property string $placeholder_var
 * @property integer $nav_item_page_id
 * @property integer $prev_id
 * @property string $json_config_values
 * @property string $json_config_cfg_values
 * @property integer $is_dirty
 * @property integer $create_user_id
 * @property integer $update_user_id
 * @property integer $timestamp_create
 * @property integer $timestamp_update
 * @property integer $sort_index
 * @property integer $is_hidden
 *
 * @todo remove scenarios?
 * @author Basil Suter <basil@nadar.io>
 */
class NavItemPageBlockItem extends \yii\db\ActiveRecord
{
    private $_olds = [];

    public static function tableName()
    {
        return 'cms_nav_item_page_block_item';
    }

    public function init()
    {
        parent::init();
        $this->on(self::EVENT_BEFORE_INSERT, [$this, 'eventBeforeInsert']);
        $this->on(self::EVENT_AFTER_INSERT, [$this, 'eventAfterInsert']);
        $this->on(self::EVENT_AFTER_UPDATE, [$this, 'eventAfterUpdate']);
        $this->on(self::EVENT_BEFORE_UPDATE, [$this, 'eventBeforeUpdate']);
        $this->on(self::EVENT_BEFORE_DELETE, [$this, 'eventBeforeDelete']);
        $this->on(self::EVENT_AFTER_DELETE, [$this, 'eventAfterDelete']);
    }

    public function rules()
    {
        return [
            [['sort_index'], 'resortIndex', 'on' => ['restcreate']],
            [['sort_index'], 'resortIndex', 'on' => ['restupdate']],
        ];
    }
    
    public static function cacheName($blockId)
    {
        return 'cmsBlockCache'.$blockId;
    }

    /**
     * resort the sort_index numbers for all items on the same: naav_item_page_id and prev_id and placholder_var.
     */
    public function resortIndex()
    {
        if (!$this->isNewRecord) {
            $this->_olds = $this->getOldAttributes();
        }
        // its a negative value, so its a last item, lets find the last index for current config
        if ($this->sort_index < 0) {
            $last = self::find()->andWhere(['nav_item_page_id' => $this->nav_item_page_id, 'placeholder_var' => $this->placeholder_var, 'prev_id' => $this->prev_id])->orderBy('sort_index DESC')->one();
            if (!$last) {
                $this->sort_index = 0;
            } else {
                $this->sort_index = $last->sort_index + 1;
            }
        } else { // its not a negative value, we have to find the positions after the current sort index and update to a higher level
            $higher = self::find()->where('sort_index >= :index', ['index' => $this->sort_index])->andWhere(['nav_item_page_id' => $this->nav_item_page_id, 'placeholder_var' => $this->placeholder_var, 'prev_id' => $this->prev_id])->all();

            foreach ($higher as $item) {
                $newSortIndex = $item->sort_index + 1;
                Yii::$app->db->createCommand()->update(self::tableName(), ['sort_index' => $newSortIndex], ['id' => $item->id])->execute();
            }
        }
    }

    public function eventBeforeUpdate()
    {
        $this->is_dirty = 1;
        $this->update_user_id = Module::getAuthorUserId();
        $this->timestamp_update = time();
    }

    public function eventAfterUpdate()
    {
        $this->updateNavItemTimesamp();
        if (!empty($this->_olds)) {
            $oldPlaceholderVar = $this->_olds['placeholder_var'];
            $oldPrevId = (int) $this->_olds['prev_id'];
            if ($oldPlaceholderVar != $this->placeholder_var || $oldPrevId != $this->prev_id) {
                $this->reindex($this->nav_item_page_id, $oldPlaceholderVar, $oldPrevId);
            }
            $this->reindex($this->nav_item_page_id, $this->placeholder_var, $this->prev_id);
            Log::add(2, ['tableName' => 'cms_nav_item_page_block_item', 'action' => 'update', 'row' => $this->id, 'pageTitle' => $this->droppedPageTitle, 'blockName' => $this->block->object->name()], 'cms_nav_item_page_block_item', $this->id);
            
            if (Yii::$app->has('cache')) {
                Yii::$app->cache->delete(static::cacheName($this->id));
            }
        }
    }

    public function eventBeforeDelete()
    {
        // delete all attached sub blocks
        $this->deleteAllSubBlocks($this->id);
        //save block data for afterDeleteEvent
        $this->_olds = $this->getOldAttributes();
        // verify if the block exists or not
        $class = ($this->block) ? $this->block->class : 'class_does_not_exists';
        // log event
        Log::add(3, ['tableName' => 'cms_nav_item_page_block_item', 'action' => 'delete', 'row' => $this->id, 'pageTitle' => $this->droppedPageTitle, 'blockName' => $this->block->object->name()], 'cms_nav_item_page_block_item', $this->id);
    }

    public function eventAfterDelete()
    {
        $this->updateNavItemTimesamp();
        if (!empty($this->_olds)) {
            $this->reindex($this->_olds['nav_item_page_id'], $this->_olds['placeholder_var'], $this->_olds['prev_id']);
        }
    }

    public function eventAfterInsert()
    {
        $this->updateNavItemTimesamp();
        $this->reindex($this->nav_item_page_id, $this->placeholder_var, $this->prev_id);
        Log::add(1, ['tableName' => 'cms_nav_item_page_block_item', 'action' => 'insert', 'row' => $this->id, 'pageTitle' => $this->droppedPageTitle, 'blockName' => $this->block->object->name()], 'cms_nav_item_page_block_item', $this->id);
    }

    public function eventBeforeInsert()
    {
        $this->timestamp_create = time();
        $this->timestamp_update = time();
        $this->create_user_id = Module::getAuthorUserId();
        if (empty($this->json_config_cfg_values)) {
            $this->json_config_cfg_values = json_encode((object) [], JSON_FORCE_OBJECT);
        }

        if (empty($this->json_config_values)) {
            $this->json_config_values = json_encode((object) [], JSON_FORCE_OBJECT);
        }
    }

    private function deleteAllSubBlocks($blockId)
    {
        if ($blockId) {
            $subBlocks = NavItemPageBlockItem::findAll(['prev_id' => $blockId]);
            foreach ($subBlocks as $block) {
                // check for attached sub blocks and start recursion
                $attachedBlocks = NavItemPageBlockItem::findAll(['prev_id' => $block->id]);
                if ($attachedBlocks) {
                    $this->deleteAllSubBlocks($block->id);
                }
                $block->delete();
            }
        }
    }

    private function reindex($navItemPageId, $placeholderVar, $prevId)
    {
        $index = 0;
        $datas = self::find()->andWhere(['nav_item_page_id' => $navItemPageId, 'placeholder_var' => $placeholderVar, 'prev_id' => $prevId])->orderBy('sort_index ASC, timestamp_create DESC')->all();
        foreach ($datas as $item) {
            Yii::$app->db->createCommand()->update(self::tableName(), ['sort_index' => $index], ['id' => $item->id])->execute();
            ++$index;
        }
    }

    public static function find()
    {
        return parent::find()->orderBy('sort_index ASC');
    }

    public function scenarios()
    {
        return [
            'restcreate' => ['block_id', 'placeholder_var', 'nav_item_page_id', 'json_config_values', 'json_config_cfg_values', 'prev_id', 'sort_index', 'is_hidden'],
            'restupdate' => ['block_id', 'placeholder_var', 'nav_item_page_id', 'json_config_values', 'json_config_cfg_values', 'prev_id', 'sort_index', 'is_hidden'],
            'default' => ['block_id', 'placeholder_var', 'nav_item_page_id', 'json_config_values', 'json_config_cfg_values', 'prev_id', 'sort_index', 'is_hidden', 'is_dirty'],
        ];
    }

    public function getBlock()
    {
        return $this->hasOne(\cmsadmin\models\Block::className(), ['id' => 'block_id']);
    }
    
    private function updateNavItemTimesamp()
    {
        // if state makes sure this does not happend when the nav item page is getting deleted and triggers the child delete process.
        if ($this->navItemPage) {
            if ($this->navItemPage->forceNavItem) {
                $this->navItemPage->forceNavItem->updateTimestamp();
            }
        }
    }

    public function getDroppedPageTitle()
    {
        // if state makes sure this does not happend when the nav item page is getting deleted and triggers the child delete process.
        if ($this->navItemPage) {
            if ($this->navItemPage->forceNavItem) {
                return $this->navItemPage->forceNavItem->title;
            }
        }

        return;
    }
    
    public function getNavItemPage()
    {
        return $this->hasOne(NavItemPage::className(), ['id' => 'nav_item_page_id']);
    }
}
