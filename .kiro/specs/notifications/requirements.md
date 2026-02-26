# Notification System - Requirements

## Feature Overview

Implement a comprehensive notification system using Laravel 12's built-in notification structure to keep customers and employees informed about order status, promotions, and important updates via SMS and database notifications.

## User Stories

### 1. As a customer, I want to receive SMS and email notifications for order updates
**So that** I stay informed about my order status without checking the app constantly

**Acceptance Criteria:**
- Customer receives SMS when order is confirmed
- Customer receives email when order is confirmed (if email exists)
- Customer receives SMS when order is being prepared
- Customer receives email when order is being prepared (if email exists)
- Customer receives SMS when order is ready for pickup/delivery
- Customer receives email when order is ready (if email exists)
- Customer receives SMS when order is out for delivery
- Customer receives email when order is out for delivery (if email exists)
- Customer receives SMS when order is completed
- Customer receives email when order is completed (if email exists)
- SMS includes order number and relevant details
- Email includes detailed order information, items, and tracking
- System gracefully handles missing email addresses (sends SMS only)

---

### 2. As a customer, I want to receive in-app notifications
**So that** I can see my notification history within the app

**Acceptance Criteria:**
- Notifications are stored in database
- Customer can view all notifications via API
- Notifications show as read/unread
- Customer can mark notifications as read
- Customer can delete notifications
- Notifications include timestamp and relevant data
- API endpoint returns paginated notifications

---

### 3. As an employee, I want to receive notifications for new orders
**So that** I can start preparing orders immediately

**Acceptance Criteria:**
- Employee receives notification when new order is placed at their branch
- Notification includes order number, items, and customer info
- Notification is stored in database
- Employee can view all notifications
- Employee can mark notifications as read

---

### 4. As a manager, I want to receive notifications for important events
**So that** I can monitor branch operations

**Acceptance Criteria:**
- Manager receives notification for high-value orders (>GHS 200)
- Manager receives notification for order cancellations
- Manager receives notification for payment failures
- Manager receives notification for customer complaints (future)
- Notifications include relevant context and action items

---

### 5. As a developer, I want a flexible notification system
**So that** adding new notification types is easy

**Acceptance Criteria:**
- Notification classes follow Laravel conventions
- Each notification type is a separate class
- Notifications support multiple channels (SMS, Email, Database)
- Notifications can be queued for async delivery
- Failed notifications are logged and retried
- All notifications sent via all available channels

---

### 6. As a customer, I want to control my notification preferences
**So that** I only receive notifications I care about

**Acceptance Criteria:**
- Customer can enable/disable SMS notifications
- Customer can enable/disable in-app notifications
- Customer can choose which order statuses trigger notifications
- Preferences are stored in database
- Preferences API endpoint available
- Default preferences are sensible (all enabled)

---

### 6. As a system, I want to track notification delivery
**So that** we can monitor and debug notification issues

**Acceptance Criteria:**
- Notification delivery status is logged
- Failed notifications are tracked
- Retry mechanism for failed SMS
- Notification metrics available (sent, delivered, failed)
- Logs include timestamp, recipient, channel, and status

---

## Notification Types

### Customer Notifications

#### 1. OrderConfirmedNotification
- **Trigger:** Order payment successful
- **Channels:** SMS, Email (if available), Database
- **Content:** Order number, total amount, estimated time, order items
- **Priority:** High

#### 2. OrderPreparingNotification
- **Trigger:** Order status changes to "preparing"
- **Channels:** SMS, Email (if available), Database
- **Content:** Order number, preparation status, estimated completion
- **Priority:** Medium

#### 3. OrderReadyNotification
- **Trigger:** Order status changes to "ready"
- **Channels:** SMS, Email (if available), Database
- **Content:** Order number, pickup/delivery instructions, branch details
- **Priority:** High

#### 4. OrderOutForDeliveryNotification
- **Trigger:** Order status changes to "out_for_delivery"
- **Channels:** SMS, Email (if available), Database
- **Content:** Order number, estimated delivery time, tracking info
- **Priority:** High

#### 5. OrderCompletedNotification
- **Trigger:** Order status changes to "completed"
- **Channels:** SMS, Email (if available), Database
- **Content:** Order number, thank you message, feedback request, receipt
- **Priority:** Medium

#### 6. OrderCancelledNotification
- **Trigger:** Order is cancelled
- **Channels:** SMS, Email (if available), Database
- **Content:** Order number, cancellation reason, refund info
- **Priority:** High

#### 7. PaymentFailedNotification
- **Trigger:** Payment processing fails
- **Channels:** SMS, Email (if available), Database
- **Content:** Order number, failure reason, retry instructions
- **Priority:** High

---

### Employee Notifications

#### 1. NewOrderNotification
- **Trigger:** New order placed at employee's branch
- **Channels:** Database
- **Content:** Order number, items, customer name, special instructions
- **Priority:** High
- **Recipients:** All active employees at the branch

#### 2. OrderCancellationNotification
- **Trigger:** Customer cancels order
- **Channels:** Database
- **Content:** Order number, cancellation reason
- **Priority:** Medium
- **Recipients:** Employees who were assigned to the order

---

### Manager Notifications

#### 1. HighValueOrderNotification
- **Trigger:** Order total exceeds GHS 200
- **Channels:** Database, SMS (optional)
- **Content:** Order number, total amount, customer info
- **Priority:** Medium
- **Recipients:** Branch manager

#### 2. PaymentIssueNotification
- **Trigger:** Payment fails or is disputed
- **Channels:** Database, SMS
- **Content:** Order number, issue details, required action
- **Priority:** High
- **Recipients:** Branch manager

---

## Technical Requirements

### 1. Database Schema

**notifications table** (Laravel default):
```php
- id (uuid)
- type (string) - Notification class name
- notifiable_type (string) - User model
- notifiable_id (bigint) - User ID
- data (json) - Notification data
- read_at (timestamp, nullable)
- created_at (timestamp)
- updated_at (timestamp)

Indexes:
- notifiable_type, notifiable_id
- read_at
- created_at
```

### notification_preferences table

Not needed for MVP. All notifications are sent via all available channels.

---

### 2. Notification Channels

#### Database Channel (Laravel Built-in)
- Store all notifications in database
- Support read/unread status
- Support soft delete
- Paginated retrieval
- Always enabled for notification history

#### Email Channel (Laravel Built-in)
- Use Laravel Mail with Blade templates
- Queue emails for async delivery
- Support HTML templates with branding
- Gracefully skip if user has no email
- Track email delivery status
- Always attempt to send if email exists

#### SMS Channel (Custom)
- Integrate with existing SMSService
- Support for Africa's Talking, Hubtel, Log
- Queue SMS for async delivery
- Retry failed SMS (3 attempts)
- Log all SMS delivery attempts
- Always attempt to send

---

### 3. Notification Structure

Each notification class should:
- Extend `Illuminate\Notifications\Notification`
- Implement `via()` method to return ['database', 'mail', SmsChannel::class]
- Implement `toDatabase()` method for database content
- Implement `toMail()` method for email content (returns MailMessage)
- Implement `toSms()` method for SMS content
- Implement `toArray()` method for API responses
- Support queueing with `ShouldQueue` interface
- Include retry logic for failed deliveries
- Gracefully handle missing email addresses

---

### 4. API Endpoints

#### Get User Notifications
```
GET /api/v1/notifications
Query params: ?page=1&per_page=20&unread_only=true
Response: Paginated list of notifications
```

#### Mark Notification as Read
```
PATCH /api/v1/notifications/{id}/read
Response: Updated notification
```

#### Mark All as Read
```
POST /api/v1/notifications/mark-all-read
Response: Success message
```

#### Delete Notification
```
DELETE /api/v1/notifications/{id}
Response: 204 No Content
```

#### Get Unread Count
```
GET /api/v1/notifications/unread-count
Response: { "count": 5 }
```

---

### 5. Integration Points

#### Order Status Changes
- Hook into Order model events (updated)
- Check if status changed
- Determine which notification to send
- Queue notification for delivery

#### Payment Processing
- Hook into payment success/failure
- Send appropriate notifications
- Include payment details

#### Employee Assignment
- Notify employees when orders are assigned
- Notify when orders are reassigned

---

## Implementation Strategy

### Phase 1: Foundation
1. Create notifications table migration
2. Create notification_preferences table migration
3. Set up Notifiable trait on User model
4. Create base notification classes
5. Create SMS notification channel

### Phase 2: Customer Notifications
1. Implement order status notifications
2. Implement payment notifications
3. Add notification triggers to Order model
4. Test notification delivery

### Phase 3: Employee Notifications
1. Implement new order notifications
2. Implement order assignment notifications
3. Add notification triggers
4. Test employee notifications

### Phase 4: API & Preferences
1. Create NotificationController
2. Implement notification API endpoints
3. Create NotificationPreference model
4. Implement preference management
5. Add preference checks to notifications

### Phase 5: Testing & Monitoring
1. Write unit tests for notifications
2. Write feature tests for API endpoints
3. Add notification logging
4. Set up monitoring for failed notifications
5. Create notification metrics dashboard (optional)

---

## Success Metrics

### Delivery Metrics
- SMS delivery rate > 95%
- Notification delivery time < 30 seconds
- Failed notification rate < 5%
- Retry success rate > 80%

### User Engagement
- Notification open rate > 60%
- Customer satisfaction with notifications > 4/5

---

## Out of Scope

The following are NOT included in this implementation:

- Notification preferences/opt-out (future phase)
- Push notifications (mobile app)
- WhatsApp notifications
- In-app real-time notifications (WebSocket)
- Notification scheduling/delayed delivery
- A/B testing for notification content
- Notification templates management UI
- Multi-language support (future phase)
- Email template customization UI
- Advanced email tracking (opens, clicks)

---

## Security & Privacy

### Data Protection
- No sensitive data in SMS (no passwords, full card numbers)
- Notification data encrypted at rest
- SMS content follows data protection regulations
- User can delete their notification history

### Rate Limiting
- Limit notifications per user: max 20/hour (prevent spam)
- Log excessive notification attempts

### Opt-out Compliance
- Critical order notifications cannot be disabled (MVP)
- Future: Allow users to opt-out of promotional notifications
- Clear unsubscribe instructions in emails

---

## Testing Requirements

### Unit Tests
- Test each notification class
- Test notification channel selection
- Test notification content generation
- Test preference checking logic

### Integration Tests
- Test notification delivery via SMS
- Test notification delivery via email
- Test notification storage in database
- Test notification retrieval via API

### Feature Tests
- Test complete order notification flow
- Test notification API endpoints
- Test notification filtering and pagination

---

## Documentation Requirements

### Developer Documentation
- Notification class structure
- How to create new notifications
- Channel configuration
- Testing notifications locally

### API Documentation
- Notification API endpoints
- Request/response formats
- Error codes and handling
- Rate limits

### User Documentation
- What notifications are sent when
- How to contact support for notification issues

---

## Dependencies

### Required
- Laravel 12 (already installed)
- Existing SMSService
- Existing User model with Notifiable trait

### Optional
- Queue system (database or Redis)
- Monitoring tools (Laravel Telescope)
- Analytics platform

---

## Migration from Current System

### Current State
- SMS sent directly via SMSService
- No notification history
- No notification preferences
- No in-app notifications

### Migration Steps
1. Create notification infrastructure
2. Add order notifications
3. Test in staging
4. Deploy to production
5. Monitor delivery rates

---

## Questions for Stakeholders

1. Should we send SMS for all order status changes or only critical ones?
2. What is the acceptable cost per SMS?
3. Should managers receive SMS or only in-app notifications?
4. Do we need notification analytics/reporting?

---

## References

- [Laravel 12 Notifications Documentation](https://laravel.com/docs/12.x/notifications)
- [Existing SMSService](../../app/Services/SMSService.php)
- [Order Model](../../app/Models/Order.php)
- [User Model](../../app/Models/User.php)
