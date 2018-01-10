<?php

namespace App\Services;

use App\Models\Message;

class MessageService{

    /**
     * 创建消息
     * @param $title
     * @param $content
     * @param $type
     * @param $subtype
     * @param $quantity
     * @param $change_phone
     * @param $from_user_id
     * @param $to_user_id
     * @throws \Exception
     */
    public function setMessage($title, $content, $type, $subtype, $quantity, $change_phone, $from_user_id, $to_user_id){
        try{
            // 创建消息
            $isSuccess = Message::create([
                'title' => $title,
                'content' => $content,
                'type' => $type,
                'subtype' => $subtype,
                'quantity' => $quantity,
                'change_phone' => $change_phone,
                'from_user_id' => $from_user_id,
                'to_user_id' => $to_user_id,
            ]);

            if(!$isSuccess){
                throw new \LogicException('创建消息失败');
            }
        }catch (\Exception $e){
            if($e instanceof \LogicException){
                $error = $e->getMessage();
            }else{
                $error = '创建消息失败';
            }
            throw new \Exception($error);
        }
    }

    /**
     * 获取消息列表
     * @param $userId
     * @return array
     */
    public function getMessageList($userId){
        $data = Message::where([
            'to_user_id' => $userId,
            'is_delete' => 0
        ])->get();

        return $data;
    }

    /**
     * 标记消息为已读
     * @param $userId
     * @param $messageId
     * @return mixed
     * @throws \Exception
     */
    public function readMessage($userId, $messageId){
        // 获得消息对象.
        $message = Message::where(['is_delete'=>0, 'id'=>$messageId])->first();

        // 判断消息是否存在,是否属于用户.
        if(!$message || $message['to_user_id'] != $userId){
            throw new \Exception("消息不存在");
        }

        // 更新.
        $message->is_read = 1;
        $message->read_time = date('Y-m-d H:i:s');
        return $message->save();
    }

    /**
     * 删除消息
     * @param $userId
     * @param $messageId
     * @return mixed
     * @throws \Exception
     */
    public function deleteMessage($userId, $messageId){
        // 获得消息对象.
        $message = Message::find($messageId);

        // 判断消息是否存在,是否属于用户.
        if(!$message || $message['to_user_id'] != $userId){
            throw new \Exception("消息不存在");
        }

        // 删除.
        $message->is_delete = 1;
        $message->delete_time = date('Y-m-d H:i:s');
        return $message->save();
    }

    /**
     * 获取未读消息数量
     * @param $userId
     * @return array
     */
    public function unReadNum($userId){
        $data = Message::where([
            'to_user_id' => $userId,
            'is_delete' => 0,
            'is_read' => 0
        ])->count();

        return $data;
    }

}