<?php
class DiarySyncReceiver_Action extends Typecho_Widget implements Widget_Interface_Do
{
    public function action()
    {
        $this->response->setHeader('Content-Type', 'application/json');

        // 1. 获取配置
        $options = Typecho_Widget::widget('Widget_Options')->plugin('DiarySyncReceiver');
        $db = Typecho_Db::get();
        $request = Typecho_Request::getInstance();

        // 2. 校验密钥
        $token = $request->get('token');
        if ($token !== $options->secretKey) {
            $this->response->throwJson(array('status' => 'error', 'msg' => 'Key Error'));
        }

        // 3. 获取数据
        $title = $request->get('title');
        $content = $request->get('content'); // Markdown 全文
        $permalink = $request->get('permalink');
        
        // 获取发送者信息
        $author = $request->get('author', '日记助手');
        $mail   = $request->get('mail', '');
        $url    = $request->get('url', '');

        $targetCid = intval($options->targetCid);

        if (empty($content) || empty($targetCid)) {
            $this->response->throwJson(array('status' => 'error', 'msg' => 'Missing Data'));
        }

        // 4. 组装评论内容 (修正：使用加粗语法)
        // 使用 Markdown 的 **文本** 语法来加粗标题，并换行
        $finalText = "**" . $title . "**\n\n" . $content;

        // 5. 写入数据库
        $commentData = array(
            'cid'       => $targetCid,
            'created'   => time(),
            'author'    => $author,
            'authorId'  => 0,
            'ownerId'   => 1,
            'mail'      => $mail,
            'url'       => $url,
            'ip'        => '127.0.0.1',
            'agent'     => 'DiarySyncBot/2.4',
            'text'      => $finalText,
            'type'      => 'comment',
            'status'    => 'approved',
            'parent'    => 0
        );

        try {
            $insertId = $db->query($db->insert('table.comments')->rows($commentData));

            // 6. 更新评论计数
            if ($insertId) {
                $row = $db->fetchRow($db->select('commentsNum')->from('table.contents')->where('cid = ?', $targetCid));
                if ($row) {
                    $db->query($db->update('table.contents')->rows(array('commentsNum' => (int)$row['commentsNum'] + 1))->where('cid = ?', $targetCid));
                }
                $this->response->throwJson(array('status' => 'success', 'id' => $insertId));
            } else {
                $this->response->throwJson(array('status' => 'error', 'msg' => 'DB Error'));
            }
        } catch (Exception $e) {
            $this->response->throwJson(array('status' => 'error', 'msg' => $e->getMessage()));
        }
    }
}