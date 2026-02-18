-- Increase MySQL max_allowed_packet to handle large Face ID images
-- Run this SQL command in your MySQL database

SET GLOBAL max_allowed_packet=16777216;  -- 16 MB

-- To make this permanent, add to your my.ini or my.cnf file:
-- [mysqld]
-- max_allowed_packet=16M

-- Verify the change:
SELECT @@max_allowed_packet;
-- Should show: 16777216
