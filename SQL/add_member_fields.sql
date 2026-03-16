-- Migration: เพิ่ม national_id และ address ใน members table
-- v1.10 — 2026-03-16

ALTER TABLE members
  ADD COLUMN national_id VARCHAR(13) NULL AFTER email,
  ADD COLUMN address TEXT NULL AFTER national_id;
