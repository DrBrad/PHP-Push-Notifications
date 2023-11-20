self.addEventListener('push', function(event){
    const json = event.data.json();

    console.log(json);

    const options = {
        body: json.body,
        icon: json.icon,
        image: json.imag,
        data: {
            url: json.url
        }
    };

    event.waitUntil(self.registration.showNotification(json.title, options));
});

self.addEventListener('notificationclick', function(event){
    event.notification.close();
    event.waitUntil(clients.openWindow(event.notification.data.url));
});
