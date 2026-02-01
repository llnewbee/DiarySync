<?php
/**
 * 日记同步发送端
 * @package DiarySyncSender
 * @author KNIFEym
 * @version 1.0.0
 * @link https://www.ymhave.com/
 */
class DiarySyncSender_Plugin implements Typecho_Plugin_Interface
{
    public static function activate()
    {
        Typecho_Plugin::factory('Widget_Contents_Post_Edit')->finishPublish = array('DiarySyncSender_Plugin', 'sendSync');
        Typecho_Plugin::factory('Widget_Contents_Post_Edit')->finishSaved = array('DiarySyncSender_Plugin', 'sendSync');
    }

    public static function deactivate(){}

    public static function config(Typecho_Widget_Helper_Form $form)
    {
        $apiUrl = new Typecho_Widget_Helper_Form_Element_Text('apiUrl', NULL, NULL, _t('主博客 API 地址'), _t('例如: https://www.ymhave.com/index.php/diary-sync-api'));
        $form->addInput($apiUrl);

        $secretKey = new Typecho_Widget_Helper_Form_Element_Text('secretKey', NULL, NULL, _t('通信密钥'), _t('必须与主博客一致'));
        $form->addInput($secretKey);

        $customName = new Typecho_Widget_Helper_Form_Element_Text('customName', NULL, NULL, _t('发送者昵称 (选填)'), _t('留空则自动获取'));
        $form->addInput($customName);

        $customMail = new Typecho_Widget_Helper_Form_Element_Text('customMail', NULL, NULL, _t('发送者邮箱 (选填)'), _t('留空则自动获取'));
        $form->addInput($customMail);
    }

    public static function personalConfig(Typecho_Widget_Helper_Form $form){}

    /**
     * 日志记录函数 (生产环境已注释，如需调试请取消注释)
     */
    private static function log($msg)
    {
        // $file = dirname(__FILE__) . '/debug_log.txt';
        // $content = date('Y-m-d H:i:s') . " " . $msg . "\n";
        // @file_put_contents($file, $content, FILE_APPEND);
    }

    /**
     * 核心发送逻辑
     * @param array $contents 表单提交的原始数组
     * @param object $edit    Typecho处理后的文章对象
     */
    public static function sendSync($contents, $edit)
    {
        // --- 1. 获取关键元数据 (从 $edit 对象拿) ---
        $cid = $edit->cid; // 文章ID
        
        // 获取状态：优先看对象属性，没有则看表单的 visibility
        $status = isset($edit->status) ? $edit->status : (isset($contents['visibility']) ? $contents['visibility'] : 'unknown');

        // self::log("🔍 触发 -> 文章ID: {$cid} | 状态: {$status}");

        // 状态检查 (Typecho 中 publish 代表公开)
        if ($status != 'publish') {
            // self::log("🚫 跳过：状态不是 publish");
            return;
        }

        try {
            $options = Typecho_Widget::widget('Widget_Options')->plugin('DiarySyncSender');
            $db = Typecho_Db::get();
            
            // --- 2. 获取作者信息 (从 $edit 对象拿 authorId 最稳) ---
            $senderName = '日记助手';
            $senderMail = '';
            
            $authorId = $edit->authorId;
            
            if ($authorId) {
                $user = $db->fetchRow($db->select()->from('table.users')->where('uid = ?', $authorId));
                if ($user) {
                    $senderName = $user['screenName'];
                    $senderMail = $user['mail'];
                }
            }

            // 用户自定义覆盖
            if (!empty($options->customName)) $senderName = $options->customName;
            if (!empty($options->customMail)) $senderMail = $options->customMail;

            $senderUrl = Typecho_Widget::widget('Widget_Options')->siteUrl;

            // --- 3. 获取内容 (从 $contents 数组拿，因为这里是最新的表单数据) ---
            $text = isset($contents['text']) ? $contents['text'] : '';
            $title = isset($contents['title']) ? $contents['title'] : '';
            $slug = isset($contents['slug']) ? $contents['slug'] : $cid; // 如果没slug就用cid

            // 再次检查关键数据
            if (empty($title) || empty($text)) {
                // self::log("❌ 终止：标题或内容为空。");
                return;
            }

            // 生成链接
            $permalink = Typecho_Common::url(
                Typecho_Router::url('post', 
                array('slug' => $slug, 'cid' => $cid), 
                Typecho_Widget::widget('Widget_Options')->index), 
                Typecho_Widget::widget('Widget_Options')->siteUrl
            );

            $postData = array(
                'token'     => $options->secretKey,
                'title'     => $title,
                'permalink' => $permalink,
                'content'   => $text, // Markdown 全文
                'author'    => $senderName,
                'mail'      => $senderMail,
                'url'       => $senderUrl
            );

            // self::log("🚀 准备发送 -> 目标: {$options->apiUrl} | 标题: {$title}");

            // --- 4. 发送请求 ---
            $ch = curl_init();
            curl_setopt($ch, CURLOPT_URL, $options->apiUrl);
            curl_setopt($ch, CURLOPT_POST, 1);
            curl_setopt($ch, CURLOPT_POSTFIELDS, http_build_query($postData));
            curl_setopt($ch, CURLOPT_RETURNTRANSFER, true);
            curl_setopt($ch, CURLOPT_SSL_VERIFYPEER, false);
            curl_setopt($ch, CURLOPT_SSL_VERIFYHOST, false);
            curl_setopt($ch, CURLOPT_TIMEOUT, 5);

            $response = curl_exec($ch);
            $error = curl_error($ch);
            // $httpCode = curl_getinfo($ch, CURLINFO_HTTP_CODE);
            curl_close($ch);

            /* 调试日志已关闭
            if ($error) {
                self::log("❌ 发送失败 CURL Error: " . $error);
            } else {
                self::log("✅ 响应 [{$httpCode}]: " . substr($response, 0, 100));
            }
            */

        } catch (Exception $e) {
            // self::log("💥 插件错误: " . $e->getMessage());
        }
    }
}