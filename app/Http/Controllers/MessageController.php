<?php

namespace App\Http\Controllers;

use App\Services\MessageService;
use Illuminate\Http\Request;

class MessageController extends Controller
{
    /**
     * 创建消息
     * @param Request $request
     * @return static
     */
    public function setMessage(Request $request){
        // 获得参数.
        $title = $request->input('title');
        $content = $request->input('content');
        $type = $request->input('type');
        $from_user_id = $request->input('from_user_id');
        $to_user_id = $request->input('to_user_id');

        if(!$title || !$content || !$type || !$from_user_id == 0 || !$to_user_id == 0){
            return $this->ajaxError("参数错误");
        }

        try{
            (new MessageService())->setMessage($title, $content, $type, $from_user_id, $to_user_id);
        }catch (\Exception $e){
            return $this->ajaxError($e->getMessage());
        }

        return $this->ajaxSuccess();
    }

    /**
     * 获取消息列表
     * @param Request $request
     * @return MessageController|array
     */
    public function getMessageList(Request $request){
        $userId = $request->user()->id;
        $data = (new MessageService())->getMessageList($userId);
        return $this->ajaxSuccess($data);
    }

    /**
     * 标记消息为已读
     * @param Request $request
     * @param $messageId
     * @return static
     */
    public function readMessage(Request $request, $messageId){
        $userId = $request->user()->id;
        try{
            $data = (new MessageService())->readMessage($userId, $messageId);
        }catch (\Exception $e){
            return $this->ajaxError($e->getMessage());
        }
        return $this->ajaxSuccess($data);
    }

    /**
     * 删除消息
     * @param Request $request
     * @param $messageId
     * @return static
     */
    public function deleteMessage(Request $request, $messageId){
        $userId = $request->user()->id;
        try{
            $data = (new MessageService())->deleteMessage($userId, $messageId);
        }catch (\Exception $e){
            return $this->ajaxError($e->getMessage());
        }
        return $this->ajaxSuccess($data);
    }

    /**
     * 获取未读消息数量
     * @param Request $request
     * @return MessageController|array
     */
    public function unReadNum(Request $request){
        $userId = $request->user()->id;
        $data = (new MessageService())->unReadNum($userId);
        return $this->ajaxSuccess($data);
    }

}
