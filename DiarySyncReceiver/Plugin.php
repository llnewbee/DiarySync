<?php
/**
 * 日记同步接收端
 * @package DiarySyncReceiver
 * @author KNIFEym
 * @version 1.0.0
 * @link https://www.ymhave.com/
 */
class DiarySyncReceiver_Plugin implements Typecho_Plugin_Interface
{
    public static function activate()
    {
        // 注册路由
        Helper::addRoute('diary_sync_route', '/diary-sync-api', 'DiarySyncReceiver_Action', 'action');
    }

    public static function deactivate(){}

    public static function config(Typecho_Widget_Helper_Form $form)
    {
        $secretKey = new Typecho_Widget_Helper_Form_Element_Text('secretKey', NULL, NULL, _t('通信密钥'), _t('请设置一个复杂的密钥，必须与发送端保持一致'));
        $form->addInput($secretKey->addRule('required', _t('必须填写密钥')));

        $targetCid = new Typecho_Widget_Helper_Form_Element_Text('targetCid', NULL, NULL, _t('目标页面CID'), _t('评论将挂载到这篇文章/页面下 (填写数字ID)'));
        $form->addInput($targetCid->addRule('required', _t('必须填写CID')));
    }

    public static function personalConfig(Typecho_Widget_Helper_Form $form){}
}