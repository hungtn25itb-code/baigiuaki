# Quản lý giảng viên - QL Học phần

Ứng dụng PHP và MySQL thực hiện các chức năng trong đề bài: thêm, xem, tìm kiếm, cập nhật và xóa giảng viên; tải ảnh đại diện; hiển thị giao diện responsive.

## Chạy trên XAMPP

1. Chép thư mục dự án vào `C:\xampp\htdocs\baigiuaki`.
2. Mở XAMPP Control Panel và khởi động **Apache** cùng **MySQL**.
3. Mở `http://localhost/phpmyadmin`, chọn **Import**, chọn file `qlhocphan.sql` trong thư mục dự án, rồi nhấn **Import**.
4. Truy cập `http://localhost/baigiuaki/`.

Script SQL tạo cơ sở dữ liệu `QLHocPhan`, bảng `GiangVien` và 10 dòng dữ liệu mẫu. Lệnh thêm dữ liệu dùng `INSERT IGNORE`, do đó có thể import lại mà không ghi đè các giảng viên đã có cùng mã.

## Cấu hình kết nối

Mặc định ứng dụng dùng cấu hình XAMPP trên máy này: MySQL tại `127.0.0.1:3307`, cơ sở dữ liệu `QLHocPhan`, tài khoản `root`, mật khẩu trống. Hãy khởi động **MySQL** trong XAMPP Control Panel trước khi mở trang web. Nếu MySQL trong XAMPP của bạn dùng cổng khác (thường là `3306`), hãy đặt biến môi trường `DB_PORT` cho cổng tương ứng. Có thể đặt thêm `DB_HOST`, `DB_NAME`, `DB_USER`, `DB_PASSWORD` để thay đổi các thông tin kết nối khác.

Ảnh tải lên được lưu trong thư mục `uploads/`. Chỉ chấp nhận JPEG, PNG, WEBP và GIF với dung lượng tối đa 5 MB.
