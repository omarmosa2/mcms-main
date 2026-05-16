MCMS System Enhancement - AI Agent Task Specification
Version: 1.0
Date: 2026-04-22
Urgency: CRITICAL
Target Completion: 2 weeks
الملخص التنفيذي
نظام MCMS (Medical Clinic Management System) محترف جداً من حيث المعمارية، لكن فيه 4 مشاكل حرجة و 12 مشكلة متوسطة/عالية بتحتاج حل قبل الإطلاق للإنتاج.
هذا الملف يحتوي على:
تفصيل كل مشكلة مع السبب والحل
Code examples للـ implementation
Test cases لكل مشكلة
Database migrations
API changes
Rollback procedures
Timeline
الجزء الأول: المشاكل الحرجة (CRITICAL - BLOCKING)
يجب حل كل هذه قبل أي شيء آخر. بدونها، لا يمكن الإطلاق للإنتاج.
مشكلة #1: Queue Number Concurrency Bug 🔴 CRITICAL
المشكلة
عندما يدخل مريضان للمجمع بنفس الوقت (concurrent)، ممكن يحصلوا على نفس رقم الطابور. هذا بيسبب فوضى في النظام.
السبب التقني
Php
Timeline:
Code
الحل المطلوب
خيار A: استخدام Database Sequences (الأفضل)
الخطوة 1: إنشاء Migration
Php
الخطوة 2: تحديث QueueEntry Model
Php
الخطوة 3: تحديث Action
Php
الخطوة 4: Test Cases
Php
الخطوة 5: Rollback Procedure
Php
مشكلة #2: Missing Clinic ID Validation (Multi-Tenancy Breach) 🔴 CRITICAL
المشكلة
دكتور من عيادة A ممكن يقدر يقرأ/يعدل بيانات مريض من عيادة B. هذا security breach خطير جداً!
السبب التقني
Php
الحل المطلوب
الخطوة 1: إنشاء Base Model
Php
الخطوة 2: تحديث جميع الـ Models
Php
الخطوة 3: Middleware للتحقق الإضافي
Php
الخطوة 4: Test Cases
Php
الخطوة 5: Code Review Checklist
Markdown
مشكلة #3: No Idempotency for Financial Operations 🔴 CRITICAL
المشكلة
لو الـ client بعت payment request مرتين (بسبب network timeout أو user clicking twice)، النظام بينشئ دفعتين! هذا financial fraud.
السبب التقني
Code
الحل المطلوب
الخطوة 1: Database Schema Modification
Php
الخطوة 2: Idempotency Service
Php
الخطوة 3: Middleware untuk Idempotency
Php
الخطوة 4: Payment Action with Idempotency
Php
الخطوة 5: API Endpoint with Idempotency
Php
الخطوة 6: Test Cases
Php
مشكلة #4: Missing Read Replica Support 🔴 CRITICAL
المشكلة
System يدعم 500+ concurrent users لكن كل الـ queries بتروح على database واحد. At 500 users:
SELECT queries بتقفل WRITE operations
Dashboard بتاخذ 5+ ثوانٍ
API responses > 1000ms
الحل المطلوب
الخطوة 1: Database Configuration
Php
الخطوة 2: Environment Variables
Env
الخطوة 3: Laravel Automatic Routing
Laravel automatically routes:
SELECT queries → Read replicas (load balanced)
INSERT/UPDATE/DELETE → Primary database
Php
الخطوة 4: Monitoring Query Routes
Php
الخطوة 5: Test Cases
Php
الجزء الثاني: مشاكل عالية الأولوية (HIGH - 1-2 أسابيع)
مشكلة #5: Missing Database Constraints 🟠 HIGH
المشكلة
التطبيق يفترض أن البيانات صحيحة، لكن database لا يفرض ذلك. مثلاً:
Invoice total_amount يمكن أن يكون سالب
paid_amount يمكن أن يكون أكبر من total_amount
clinic_id يمكن أن يشير لـ clinic غير موجودة
الحل
إنشاء Migration للـ Constraints
Php
مشكلة #6: No API Documentation 🟠 HIGH
الحل: OpenAPI/Swagger
Php
مشكلة #7: Missing Cache Strategy 🟠 HIGH
الحل
Cache Application-Level Queries
Php
مشكلة #8: Financial Module Too Basic 🟠 HIGH
Missing Features
Markdown
مشكلة #9: Inventory Module Incomplete 🟠 HIGH
Missing Features
Markdown
مشكلة #10: Diagnostics Module Incomplete 🟠 HIGH
Missing Features
Markdown
مشكلة #11: No Approval Workflows 🟠 HIGH
الحل: Implement Workflow Engine
Php
مشكلة #12: No Monitoring/Alerting 🟠 HIGH
الحل: Setup Prometheus + Grafana
Yaml
الجزء الثالث: Implementation Roadmap
Timeline: 2-Week Delivery Plan
Code
Testing Checklist
Markdown
Deployment Checklist
Markdown
Summary
Problem
Severity
Status
Timeline
Owner
Queue Concurrency
🔴 CRITICAL
To Fix
Day 1-2
Backend
Clinic ID Validation
🔴 CRITICAL
To Fix
Day 3-4
Backend
Idempotency
🔴 CRITICAL
To Fix
Day 5
Backend
Read Replicas
🔴 CRITICAL
To Fix
Week 2 Day 1-2
Backend
DB Constraints
🟠 HIGH
To Fix
Week 2 Day 3
DBA
API Docs
🟠 HIGH
To Fix
Week 2 Day 3
Backend
Caching
🟠 HIGH
To Fix
Week 2 Day 4
Backend
Financial Module
🟠 HIGH
To Plan
Week 3+
Finance
Inventory
🟠 HIGH
To Plan
Week 3+
Inventory
Diagnostics
🟠 HIGH
To Plan
Week 3+
Diagnostics
Workflows
🟠 HIGH
To Plan
Week 3+
Backend
Monitoring
🟠 HIGH
To Setup
Week 2 Day 5
DevOps
Contact & Questions
If AI agent encounters issues:
Check test cases for expected behavior
Review database schema changes carefully
Ensure backward compatibility
Get security review for permission changes
Validate performance improvements
End of Specification
This document is ready to be given to an AI agent for implementation.
Good luck! 🚀
