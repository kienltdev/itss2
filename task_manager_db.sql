-- phpMyAdmin SQL Dump
-- version 5.2.1
-- https://www.phpmyadmin.net/
--
-- Máy chủ: localhost:3306
-- Thời gian đã tạo: Th5 15, 2025 lúc 02:06 AM
-- Phiên bản máy phục vụ: 8.0.30
-- Phiên bản PHP: 8.1.10

SET SQL_MODE = "NO_AUTO_VALUE_ON_ZERO";
START TRANSACTION;
SET time_zone = "+00:00";


/*!40101 SET @OLD_CHARACTER_SET_CLIENT=@@CHARACTER_SET_CLIENT */;
/*!40101 SET @OLD_CHARACTER_SET_RESULTS=@@CHARACTER_SET_RESULTS */;
/*!40101 SET @OLD_COLLATION_CONNECTION=@@COLLATION_CONNECTION */;
/*!40101 SET NAMES utf8mb4 */;

--
-- Cơ sở dữ liệu: `task_manager_db`
--

-- --------------------------------------------------------

--
-- Cấu trúc bảng cho bảng `main_tasks`
--

CREATE TABLE `main_tasks` (
  `id` int UNSIGNED NOT NULL COMMENT 'ID duy nhất của nhiệm vụ chính',
  `name` varchar(255) COLLATE utf8mb4_unicode_ci NOT NULL COMMENT 'Tên của nhiệm vụ chính',
  `color` varchar(20) COLLATE utf8mb4_unicode_ci DEFAULT 'pink' COMMENT 'Màu sắc đại diện cho nhiệm vụ',
  `icon_name` varchar(50) COLLATE utf8mb4_unicode_ci DEFAULT 'tag' COMMENT 'Tên biểu tượng đại diện',
  `subject_tag` varchar(100) COLLATE utf8mb4_unicode_ci DEFAULT NULL COMMENT 'Tag môn học hoặc chủ đề của nhiệm vụ',
  `deadline` datetime DEFAULT NULL COMMENT 'Hạn nộp của nhiệm vụ',
  `priority` enum('low','medium','high') COLLATE utf8mb4_unicode_ci NOT NULL DEFAULT 'medium' COMMENT 'Mức độ ưu tiên của nhiệm vụ',
  `status` enum('todo','inprogress','completed') COLLATE utf8mb4_unicode_ci NOT NULL DEFAULT 'todo' COMMENT 'Trạng thái hiện tại của nhiệm vụ chính',
  `reminder_setting` varchar(10) COLLATE utf8mb4_unicode_ci DEFAULT 'none' COMMENT 'Cài đặt nhắc nhở sớm',
  `repeat_interval` enum('none','hourly','daily','weekly','monthly') COLLATE utf8mb4_unicode_ci NOT NULL DEFAULT 'none' COMMENT 'Tần suất lặp lại nhiệm vụ',
  `repeat_count` int UNSIGNED DEFAULT NULL COMMENT 'Số lần lặp lại',
  `created_at` timestamp NOT NULL DEFAULT CURRENT_TIMESTAMP COMMENT 'Thời gian tạo nhiệm vụ',
  `updated_at` timestamp NOT NULL DEFAULT CURRENT_TIMESTAMP ON UPDATE CURRENT_TIMESTAMP COMMENT 'Thời gian cập nhật nhiệm vụ lần cuối'
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci COMMENT='Lưu trữ các nhiệm vụ chính';

--
-- Đang đổ dữ liệu cho bảng `main_tasks`
--

INSERT INTO `main_tasks` (`id`, `name`, `color`, `icon_name`, `subject_tag`, `deadline`, `priority`, `status`, `reminder_setting`, `repeat_interval`, `repeat_count`, `created_at`, `updated_at`) VALUES
(1, 'Hoàn thành Báo cáo Dự án Alpha', 'pink', 'briefcase', 'Dự án Alpha', '2025-05-15 10:15:34', 'high', 'inprogress', '1h', 'none', NULL, '2025-05-15 01:15:34', '2025-05-15 01:15:34'),
(2, 'Nộp bài tập Cơ sở dữ liệu Tuần 3', 'purple', 'academic-cap', 'CSDL', '2025-05-16 08:15:34', 'high', 'todo', '30m', 'none', NULL, '2025-05-15 01:15:34', '2025-05-15 01:15:34'),
(3, 'Lên kế hoạch cho buổi họp nhóm', 'blue', 'user-group', 'Công việc chung', '2025-05-15 07:15:34', 'medium', 'completed', 'none', 'none', NULL, '2025-05-15 01:15:34', '2025-05-15 01:17:13'),
(4, 'Task test nhắc nhở 5 phút', 'green', 'tag', 'Testing', '2025-05-15 08:25:34', 'low', 'todo', '5m', 'none', NULL, '2025-05-15 01:15:34', '2025-05-15 01:15:34'),
(5, 'áddas', 'orange', 'academic-cap', 'adsdsa', '2025-05-15 08:23:00', 'medium', 'completed', '5m', 'hourly', 3, '2025-05-15 01:16:50', '2025-05-15 01:33:08'),
(6, 'test', 'pink', 'academic-cap', 'toán', '2025-05-15 08:36:00', 'medium', 'todo', '5m', 'none', NULL, '2025-05-15 01:29:40', '2025-05-15 01:29:40');

-- --------------------------------------------------------

--
-- Cấu trúc bảng cho bảng `notifications`
--

CREATE TABLE `notifications` (
  `id` int UNSIGNED NOT NULL COMMENT 'ID duy nhất của thông báo',
  `task_id` int UNSIGNED DEFAULT NULL COMMENT 'ID của nhiệm vụ chính liên quan (nếu có)',
  `type` enum('reminder','overdue','general','task_created','task_updated') COLLATE utf8mb4_unicode_ci NOT NULL DEFAULT 'general' COMMENT 'Loại thông báo',
  `message` text COLLATE utf8mb4_unicode_ci NOT NULL COMMENT 'Nội dung thông báo',
  `is_read` tinyint(1) NOT NULL DEFAULT '0' COMMENT 'Trạng thái đã đọc (0: chưa đọc, 1: đã đọc)',
  `created_at` timestamp NOT NULL DEFAULT CURRENT_TIMESTAMP COMMENT 'Thời gian tạo thông báo',
  `notify_at` timestamp NULL DEFAULT NULL COMMENT 'Thời điểm cụ thể để hiển thị thông báo (nếu cần lên lịch)'
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci COMMENT='Lưu trữ các thông báo cho người dùng';

--
-- Đang đổ dữ liệu cho bảng `notifications`
--

INSERT INTO `notifications` (`id`, `task_id`, `type`, `message`, `is_read`, `created_at`, `notify_at`) VALUES
(1, 5, 'task_created', 'Nhiệm vụ mới đã được tạo: \'áddas\'', 1, '2025-05-15 01:16:50', '2025-05-15 01:16:50'),
(2, 4, 'overdue', 'Nhiệm vụ \'Task test nhắc nhở 5 phút\' (Môn: Testing) đã quá hạn 3 phút. Hãy hoàn thành ngay!', 1, '2025-05-15 01:28:44', '2025-05-15 01:28:44'),
(3, 5, 'overdue', 'Nhiệm vụ \'áddas\' (Môn: adsdsa) đã quá hạn 6 phút. Hãy hoàn thành ngay!', 1, '2025-05-15 01:28:44', '2025-05-15 01:28:44'),
(4, 6, 'task_created', 'Nhiệm vụ mới đã được tạo: \'test\'', 1, '2025-05-15 01:29:40', '2025-05-15 01:29:40'),
(5, 6, 'reminder', 'Nhắc nhở: Nhiệm vụ \'test\' (Môn: toán) sắp đến hạn vào 15/05/2025, 08:36.', 1, '2025-05-15 01:31:10', '2025-05-14 18:31:00'),
(6, 6, 'overdue', 'Nhiệm vụ \'test\' (Môn: toán) đã quá hạn vài giây. Hãy hoàn thành ngay!', 1, '2025-05-15 01:36:17', '2025-05-15 01:36:17');

-- --------------------------------------------------------

--
-- Cấu trúc bảng cho bảng `sub_tasks`
--

CREATE TABLE `sub_tasks` (
  `id` int UNSIGNED NOT NULL COMMENT 'ID duy nhất của nhiệm vụ con',
  `main_task_id` int UNSIGNED NOT NULL COMMENT 'ID của nhiệm vụ chính mà nhiệm vụ con này thuộc về',
  `name` varchar(255) COLLATE utf8mb4_unicode_ci NOT NULL COMMENT 'Tên của nhiệm vụ con',
  `status` enum('todo','inprogress','completed') COLLATE utf8mb4_unicode_ci NOT NULL DEFAULT 'todo' COMMENT 'Trạng thái hiện tại của nhiệm vụ con',
  `created_at` timestamp NOT NULL DEFAULT CURRENT_TIMESTAMP COMMENT 'Thời gian tạo nhiệm vụ con',
  `updated_at` timestamp NOT NULL DEFAULT CURRENT_TIMESTAMP ON UPDATE CURRENT_TIMESTAMP COMMENT 'Thời gian cập nhật nhiệm vụ con lần cuối'
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci COMMENT='Lưu trữ các nhiệm vụ con (subtasks)';

--
-- Đang đổ dữ liệu cho bảng `sub_tasks`
--

INSERT INTO `sub_tasks` (`id`, `main_task_id`, `name`, `status`, `created_at`, `updated_at`) VALUES
(1, 1, 'Thu thập số liệu quý 1', 'completed', '2025-05-15 01:15:34', '2025-05-15 01:15:34'),
(2, 1, 'Phân tích dữ liệu', 'inprogress', '2025-05-15 01:15:34', '2025-05-15 01:15:34'),
(3, 2, 'Làm bài tập lý thuyết chương 5', 'todo', '2025-05-15 01:15:34', '2025-05-15 01:15:34'),
(4, 3, 'ads', 'completed', '2025-05-15 01:17:35', '2025-05-15 01:32:57'),
(5, 3, 'ewq', 'completed', '2025-05-15 01:32:54', '2025-05-15 01:32:56');

--
-- Chỉ mục cho các bảng đã đổ
--

--
-- Chỉ mục cho bảng `main_tasks`
--
ALTER TABLE `main_tasks`
  ADD PRIMARY KEY (`id`);

--
-- Chỉ mục cho bảng `notifications`
--
ALTER TABLE `notifications`
  ADD PRIMARY KEY (`id`),
  ADD KEY `idx_notifications_task_id` (`task_id`),
  ADD KEY `idx_notifications_is_read_created_at` (`is_read`,`created_at` DESC);

--
-- Chỉ mục cho bảng `sub_tasks`
--
ALTER TABLE `sub_tasks`
  ADD PRIMARY KEY (`id`),
  ADD KEY `fk_sub_tasks_main_task_id_idx` (`main_task_id`);

--
-- AUTO_INCREMENT cho các bảng đã đổ
--

--
-- AUTO_INCREMENT cho bảng `main_tasks`
--
ALTER TABLE `main_tasks`
  MODIFY `id` int UNSIGNED NOT NULL AUTO_INCREMENT COMMENT 'ID duy nhất của nhiệm vụ chính', AUTO_INCREMENT=7;

--
-- AUTO_INCREMENT cho bảng `notifications`
--
ALTER TABLE `notifications`
  MODIFY `id` int UNSIGNED NOT NULL AUTO_INCREMENT COMMENT 'ID duy nhất của thông báo', AUTO_INCREMENT=7;

--
-- AUTO_INCREMENT cho bảng `sub_tasks`
--
ALTER TABLE `sub_tasks`
  MODIFY `id` int UNSIGNED NOT NULL AUTO_INCREMENT COMMENT 'ID duy nhất của nhiệm vụ con', AUTO_INCREMENT=6;

--
-- Các ràng buộc cho các bảng đã đổ
--

--
-- Các ràng buộc cho bảng `notifications`
--
ALTER TABLE `notifications`
  ADD CONSTRAINT `fk_notifications_task_id` FOREIGN KEY (`task_id`) REFERENCES `main_tasks` (`id`) ON DELETE SET NULL ON UPDATE CASCADE;

--
-- Các ràng buộc cho bảng `sub_tasks`
--
ALTER TABLE `sub_tasks`
  ADD CONSTRAINT `fk_sub_tasks_main_task_id` FOREIGN KEY (`main_task_id`) REFERENCES `main_tasks` (`id`) ON DELETE CASCADE ON UPDATE CASCADE;
COMMIT;

/*!40101 SET CHARACTER_SET_CLIENT=@OLD_CHARACTER_SET_CLIENT */;
/*!40101 SET CHARACTER_SET_RESULTS=@OLD_CHARACTER_SET_RESULTS */;
/*!40101 SET COLLATION_CONNECTION=@OLD_COLLATION_CONNECTION */;
