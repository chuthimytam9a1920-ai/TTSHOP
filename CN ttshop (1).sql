ALTER TABLE `danhgia`
  ADD CONSTRAINT `fk_danhgia_donhang` 
  FOREIGN KEY (`donhang_id`) REFERENCES `donhang`(`id`) ON DELETE CASCADE;

ALTER TABLE `danhgia`
  ADD CONSTRAINT `fk_danhgia_users` 
  FOREIGN KEY (`user_id`) REFERENCES `users`(`id`);

ALTER TABLE `diachi_khachhang`
  ADD CONSTRAINT `fk_diachi_users` 
  FOREIGN KEY (`user_id`) REFERENCES `users`(`id`) ON DELETE CASCADE;

ALTER TABLE `chat_conversations`
  ADD CONSTRAINT `fk_chat_conversations_users` 
  FOREIGN KEY (`user_id`) REFERENCES `users`(`id`) ON DELETE SET NULL;

ALTER TABLE `orders`
  ADD CONSTRAINT `fk_orders_users` 
  FOREIGN KEY (`user_id`) REFERENCES `users`(`id`);

ALTER TABLE `order_items`
  ADD CONSTRAINT `fk_order_items_orders` 
  FOREIGN KEY (`order_id`) REFERENCES `orders`(`id`) ON DELETE CASCADE;

ALTER TABLE `order_items`
  ADD CONSTRAINT `fk_order_items_products` 
  FOREIGN KEY (`product_id`) REFERENCES `products`(`id`);