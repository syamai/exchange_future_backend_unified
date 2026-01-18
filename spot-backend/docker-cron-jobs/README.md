# 🐳 Laravel Cron Job Container – `docker-cron-jobs`

Docker setup dành riêng để chạy các Laravel scheduled jobs (`php artisan schedule:run`) trên một server chuyên dụng, **không ảnh hưởng đến backend chính**.

---

## ✅ Tính năng chính

- 🧱 **Tách riêng container** chỉ để chạy cron — không ảnh hưởng đến backend chính
- 🔄 **Tự động mount source backend** vào `/app` (dùng Docker volume)
- 👀 **Cron tự động reload khi `crontab.txt` thay đổi** (dùng `inotify`)
- 🛠 **Tự chạy `php artisan migrate`** khi database đã sẵn sàng [Đã tắt để tránh conflict or lỗi chung, nên chạy php artisan migrate bên server chính]
- 🗑 **Log cron theo ngày**, tự động dọn các file log cũ hơn **7 ngày**

---

## 🚀 Hướng dẫn triển khai

```bash
# Bước 1: Copy source cron job vào thư mục cha
cp -r docker-cron-jobs/ ../
cd ../docker-cron-jobs

# Bước 2: Cấp quyền thực thi cho các file script
chmod +x deploy.sh
chmod +x entrypoint.sh
chmod +x watch-crontab.sh

# Bước 3: Chạy container
./deploy.sh
