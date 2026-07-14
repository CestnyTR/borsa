// PM2 process manager — tüm arka plan süreçlerini TEK komutla çalıştırır.
//
//   Kurulum (bir kez):  npm install -g pm2
//   Başlat:             pm2 start ecosystem.config.cjs
//   Durum:              pm2 status
//   Loglar:             pm2 logs           (tek süreç: pm2 logs harem)
//   Yeniden başlat:     pm2 restart all
//   Durdur / sil:       pm2 delete all
//   Sunucu açılışında otomatik başlat:  pm2 startup && pm2 save
//
// SUNUCU (VDS) için: web'i nginx + php-fpm sunar; PM2 yalnızca arka plan
// süreçlerini (reverb, queue, cron, harem) çalıştırır — varsayılan budur.
// Yerelde dahili web sunucusunu da istersen:  APP_SERVE=1 pm2 start ecosystem.config.cjs

const base = {
    interpreter: 'php',   // php artisan <args>
    script: 'artisan',
    cwd: __dirname,
    autorestart: true,
    max_restarts: 50,
    restart_delay: 2000,
    time: true,           // log satırlarına zaman damgası
};

const apps = [
    { ...base, name: 'reverb', args: 'reverb:start' },                                  // websocket santralı
    { ...base, name: 'queue',  args: 'queue:work --sleep=1 --tries=1 --max-time=3600' }, // broadcast job'ları
    { ...base, name: 'cron',   args: 'schedule:work' },                                 // market:update + gold:fetch
    { ...base, name: 'harem',  args: 'harem:listen' },                                  // HAREM canlı akış
];

// Yerel geliştirme için dahili web sunucusu — sadece APP_SERVE=1 ise.
// Üretimde web'i nginx + php-fpm sunar, bu yüzden varsayılan olarak kapalı.
if (process.env.APP_SERVE === '1') {
    apps.unshift({
        ...base,
        name: 'web',
        args: `serve --host=${process.env.APP_HOST || '127.0.0.1'} --port=${process.env.APP_PORT || '8000'}`,
    });
}

module.exports = { apps };
