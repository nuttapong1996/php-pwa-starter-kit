<?php
// เรียกใช้ Lib ของ WebPush
use Minishlink\WebPush\Subscription;
use Minishlink\WebPush\WebPush;

// เรียกใช้ไฟล์ autoload.php ของ Composer
require ("../vendor/autoload.php");
header('Content-Type: application/json; charset=utf-8');


// ตรวจสอบการส่งข้อมูลจาก form
if(!empty($_POST['title']) && !empty($_POST['body']) && !empty($_POST['url'])) {
        // เรียกใช้ไฟล์ connect_db.php เชื่อมต่อฐานข้อมูล
        require '../includes/connect_db.php';
        // ประกาศตัวแปร username เพื่อเก็บชื่อผู้ใช้จาก session
        // คําสั่ง SQL เพื่อดึงข้อมูล endpoint , p256dh , authKey จาก push_subscribers ของผู้ใช้โดยอิงจาก username ของผู้ใช้ที่ได้สมัครเข้ามา
        $sql = "SELECT username, endpoint , p256dh , authKey FROM push_subscribers";
        $stmt = $conn->prepare($sql);
        $stmt->execute();
        $endpoints = $stmt->fetchAll(PDO::FETCH_ASSOC);

        // กำหนดตัวแปร auth สำหรับใช้ในการ ส่งแจ้งเตือน โดยเรียกใช้งานจาก ไฟล์ public_key.text และ private_key.text ที่จัดเก็บไว้ในโฟลเดอร์ includes
            $auth = [
                'VAPID' => [
                    'subject' => 'mailto:me@website.com', // can be a mailto: or your website address
                    'publicKey' => trim(file_get_contents('../includes/key/public_key.text')),
                    'privateKey' => trim(file_get_contents('../includes/key/private_key.text'))
                ],
            ];

            // สร้างตัวแปร webPush เพื่อใช้ส่งแจ้งเตือน
            $webPush = new WebPush($auth);

            // วนลูปเพื่อส่งแจ้งเตือนให้แต่ละ endpoint
            foreach($endpoints as $endpoint){
                $subscription = Subscription::create([
                    'endpoint' => $endpoint['endpoint'],
                    'keys' => [
                        'p256dh' => $endpoint['p256dh'],
                        'auth' => $endpoint['authKey']
                    ]
                ]);
                // กำหนดตัวแปร message สำหรับใช้ในการ ส่งแจ้งเตือนโดยกําหนดค่า title , body , url จาก form
                $message = [
                    'title' => $_POST['title'] .' ถึงคุณ ' . $endpoint['username'],
                    'body' =>$_POST['body'],
                    'url' => $_POST['url'].'?username=' . $endpoint['username']
                ];

                $webPush->queueNotification(
                    $subscription,
                    json_encode($message)
                );
            }
            
            // วนลูปเพื่อส่งแจ้งเตือน
            foreach($webPush->flush() as $report) {
                if($report->isSuccess()){
                    //ทำการส่งค่า status success กลับไปในกรณีที่มีการใช้ fetch api ในการเช็คสถานะการส่งแจ้งเตือน
                    echo json_encode(['status' => 'success']);
                        
                }else{
                    //ทำการส่งค่า status error กลับไปในกรณีที่มีการใช้ fetch api ในการเช็คสถานะการส่งแจ้งเตือน
                    echo json_encode([
                        'status' => 'error' , 
                        'message' => 'something went wrong'
                    ]);
                }
            }
}else{
    echo json_encode(['status' => 'error' , 'message' => 'invalid parameter , something went wrong']);
}