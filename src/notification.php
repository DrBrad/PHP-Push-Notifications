<?php
    ini_set('display_errors', 1);
    ini_set('display_startup_errors', 1);
    error_reporting(E_ALL);

    if(file_exists('vapid.json')){
        $keys = json_decode(file_get_contents('vapid.json'), true);

    }else{
        require_once 'handlers/jwk.php';
        $jwk = new JWK();
        $jwk->create();

        $keys = [
            'publicKey' => $jwk->public_to_base64(),
            'privateKey' => $jwk->private_to_base64()
        ];

        file_put_contents('vapid.json', json_encode($keys, JSON_PRETTY_PRINT));
    }
?>
<!DOCTYPE html>
<html lang="en">
<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0">
    <title>Push Notifications Example</title>
</head>
    <body>
        <button onclick="subscribeToPush()">Subscribe to Push Notifications</button><br>
        <?php echo $keys['publicKey']; ?>

        <script>
            function subscribeToPush() {
                if ('serviceWorker' in navigator && 'PushManager' in window) {
                    navigator.serviceWorker.register('/notification-service.js')
                        .then(function(registration) {
                            console.log('Service Worker registered with scope:', registration);

                            return registration.pushManager.subscribe({
                                userVisibleOnly: true,
                                applicationServerKey: "<?php echo $keys['publicKey']; ?>"
                                //applicationServerKey: urlBase64ToUint8Array("<?php echo $keys['publicKey']; ?>")
                            });
                        })
                        .then(function(subscription) {
                            // Send the subscription details to the server (save it in the database)
                            console.log('Subscription:', subscription);
                            sendSubscriptionToServer(subscription);
                        })
                        .catch(function(error) {
                            console.error('Error during push subscription:', error);
                        });
                } else {
                    console.error('Push notifications not supported');
                }
            }

            function sendSubscriptionToServer(subscription) {
                // Send the subscription details to the server using an AJAX request or other method
                // Replace 'your-server-url' with the actual URL where your PHP script is hosted
                fetch('/notification-subscribe', {
                    method: 'POST',
                    headers: {
                        'Content-Type': 'application/json',
                    },
                    body: JSON.stringify(subscription),
                })
                .then(function(response) {
                    console.log('Subscription details sent to server:', response);
                })
                .catch(function(error) {
                    console.error('Error sending subscription details to server:', error);
                });
            }

            function urlBase64ToUint8Array(base64String) {
                const padding = '='.repeat((4 - base64String.length % 4) % 4);
                const base64 = (base64String + padding)
                    .replace(/\-/g, '+')
                    .replace(/_/g, '/');

                const rawData = window.atob(base64);
                const outputArray = new Uint8Array(rawData.length);

                for (var i = 0; i < rawData.length; ++i) {
                    outputArray[i] = rawData.charCodeAt(i);
                }
                return outputArray;
            }
        </script>
    </body>
</html>
