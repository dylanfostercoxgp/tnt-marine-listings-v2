<?php
if ( ! defined( 'ABSPATH' ) ) exit;

function tnt_marine_inquiry_form( $listing_id, $listing_title ) {
    ob_start();
    ?>
    <div class="tnt-inquiry-wrap" id="tnt-inquiry">
        <h3>Inquire About This Vessel</h3>
        <div id="tnt-inquiry-message" class="tnt-inquiry-notice" style="display:none;"></div>
        <div class="tnt-inquiry-form">
            <div class="tnt-form-row">
                <div class="tnt-form-group">
                    <label for="tnt_name">Full Name <span>*</span></label>
                    <input type="text" id="tnt_name" placeholder="Your name" required>
                </div>
                <div class="tnt-form-group">
                    <label for="tnt_email">Email Address <span>*</span></label>
                    <input type="email" id="tnt_email" placeholder="your@email.com" required>
                </div>
            </div>
            <div class="tnt-form-row">
                <div class="tnt-form-group">
                    <label for="tnt_phone">Phone Number</label>
                    <input type="tel" id="tnt_phone" placeholder="(555) 000-0000">
                </div>
                <div class="tnt-form-group">
                    <label for="tnt_subject">Subject</label>
                    <input type="text" id="tnt_subject" value="Inquiry: <?php echo esc_attr( $listing_title ); ?>" readonly>
                </div>
            </div>
            <div class="tnt-form-group">
                <label for="tnt_message">Message <span>*</span></label>
                <textarea id="tnt_message" rows="5" placeholder="I am interested in this vessel and would like more information..."><?php echo esc_textarea( 'I am interested in the ' . $listing_title . ' and would like more information.' ); ?></textarea>
            </div>
            <input type="hidden" id="tnt_listing_id" value="<?php echo intval( $listing_id ); ?>">
            <button type="button" id="tnt-inquiry-submit" class="tnt-btn-primary">Send Inquiry</button>
        </div>
    </div>
    <?php
    return ob_get_clean();
}

function tnt_marine_process_inquiry() {
    check_ajax_referer( 'tnt_marine_nonce', 'nonce' );

    $name       = sanitize_text_field( wp_unslash( $_POST['name']       ?? '' ) );
    $email      = sanitize_email( wp_unslash( $_POST['email']           ?? '' ) );
    $phone      = sanitize_text_field( wp_unslash( $_POST['phone']      ?? '' ) );
    $message    = sanitize_textarea_field( wp_unslash( $_POST['message'] ?? '' ) );
    $listing_id = intval( $_POST['listing_id'] ?? 0 );

    if ( ! $name || ! $email || ! $message || ! is_email( $email ) ) {
        wp_send_json_error( [ 'message' => 'Please fill in all required fields with valid information.' ] );
    }

    $listing_title = get_the_title( $listing_id );
    $listing_url   = get_permalink( $listing_id );
    $to_email      = 'dylan@coxgp.com';

    // Listing meta
    $price  = get_post_meta( $listing_id, '_tnt_price',    true );
    $year   = get_post_meta( $listing_id, '_tnt_year',     true );
    $length = get_post_meta( $listing_id, '_tnt_length',   true );
    $hours  = get_post_meta( $listing_id, '_tnt_hours',    true );
    $power_raw = 0;
    $engine_count = intval( get_post_meta( $listing_id, '_tnt_engine_count', true ) ) ?: 1;
    for ( $e = 1; $e <= $engine_count; $e++ ) {
        $power_raw += intval( get_post_meta( $listing_id, '_tnt_engine_power_' . $e, true ) );
    }
    $location = get_post_meta( $listing_id, '_tnt_location', true );

    // Featured image URL
    $featured_id  = (int) get_post_thumbnail_id( $listing_id );
    if ( ! $featured_id ) {
        $gallery_raw = get_post_meta( $listing_id, '_tnt_gallery_ids', true );
        if ( $gallery_raw ) {
            $gids        = array_filter( array_map( 'intval', explode( ',', $gallery_raw ) ) );
            $featured_id = reset( $gids );
        }
    }
    $featured_url = $featured_id ? wp_get_attachment_image_url( $featured_id, 'large' ) : '';

    // Format display values
    $price_fmt  = $price  ? '$' . number_format( floatval( $price ) ) : '&mdash;';
    $year_fmt   = $year   ?: '&mdash;';
    $length_fmt = $length ? $length . 'ft' : '&mdash;';
    $hours_fmt  = $hours  ?: '&mdash;';
    $power_fmt  = $power_raw ? $power_raw . 'hp' : '&mdash;';

    $subject = 'New Inquiry: ' . $listing_title;

    ob_start();
    ?>
<!DOCTYPE html>
<html lang="en">
<head><meta charset="UTF-8"><meta name="viewport" content="width=device-width,initial-scale=1.0"></head>
<body style="margin:0;padding:0;background:#f0f0f0;font-family:Arial,sans-serif;">
<table width="100%" cellpadding="0" cellspacing="0" style="background:#f0f0f0;padding:30px 0;">
  <tr>
    <td align="center">
      <table width="600" cellpadding="0" cellspacing="0" style="max-width:600px;width:100%;background:#ffffff;border-radius:8px;overflow:hidden;box-shadow:0 2px 12px rgba(0,0,0,0.10);">

        <!-- HEADER -->
        <tr>
          <td style="background:#1a2e4a;padding:24px 32px;">
            <p style="margin:0;font-size:11px;color:rgba(255,255,255,0.6);text-transform:uppercase;letter-spacing:0.08em;">New Inquiry Received</p>
            <h1 style="margin:6px 0 0;font-size:22px;color:#ffffff;font-weight:700;line-height:1.3;">TNT Custom Marine</h1>
          </td>
        </tr>

        <?php if ( $featured_url ) : ?>
        <!-- FEATURED IMAGE -->
        <tr>
          <td style="padding:0;line-height:0;">
            <img src="<?php echo esc_url( $featured_url ); ?>" width="600" alt="<?php echo esc_attr( $listing_title ); ?>" style="display:block;width:100%;max-height:220px;object-fit:cover;">
          </td>
        </tr>
        <?php endif; ?>

        <!-- LISTING TITLE BAR -->
        <tr>
          <td style="background:#cc2129;padding:14px 32px;">
            <table width="100%" cellpadding="0" cellspacing="0">
              <tr>
                <td style="vertical-align:middle;">
                  <h2 style="margin:0;font-size:18px;font-weight:800;color:#ffffff;line-height:1.2;"><?php echo esc_html( $listing_title ); ?></h2>
                  <?php if ( $location ) : ?>
                  <p style="margin:4px 0 0;font-size:13px;color:rgba(255,255,255,0.75);"><?php echo esc_html( $location ); ?></p>
                  <?php endif; ?>
                </td>
                <?php if ( $price ) : ?>
                <td align="right" style="white-space:nowrap;vertical-align:middle;">
                  <span style="font-size:22px;font-weight:800;color:#ffffff;"><?php echo esc_html( '$' . number_format( floatval( $price ) ) ); ?></span>
                </td>
                <?php endif; ?>
              </tr>
            </table>
          </td>
        </tr>

        <!-- SPECS ROW -->
        <tr>
          <td style="background:#f7f7f7;padding:16px 32px;border-bottom:1px solid #e5e5e5;">
            <table width="100%" cellpadding="0" cellspacing="0">
              <tr>
                <td width="25%" style="text-align:center;padding:8px 0;border-right:1px solid #e0e0e0;">
                  <p style="margin:0;font-size:10px;text-transform:uppercase;letter-spacing:0.06em;color:#999;">Year</p>
                  <p style="margin:4px 0 0;font-size:15px;font-weight:700;color:#1a2e4a;"><?php echo esc_html( $year_fmt ); ?></p>
                </td>
                <td width="25%" style="text-align:center;padding:8px 0;border-right:1px solid #e0e0e0;">
                  <p style="margin:0;font-size:10px;text-transform:uppercase;letter-spacing:0.06em;color:#999;">Length</p>
                  <p style="margin:4px 0 0;font-size:15px;font-weight:700;color:#1a2e4a;"><?php echo esc_html( $length_fmt ); ?></p>
                </td>
                <td width="25%" style="text-align:center;padding:8px 0;border-right:1px solid #e0e0e0;">
                  <p style="margin:0;font-size:10px;text-transform:uppercase;letter-spacing:0.06em;color:#999;">Engine Hours</p>
                  <p style="margin:4px 0 0;font-size:15px;font-weight:700;color:#1a2e4a;"><?php echo esc_html( $hours_fmt ); ?></p>
                </td>
                <td width="25%" style="text-align:center;padding:8px 0;">
                  <p style="margin:0;font-size:10px;text-transform:uppercase;letter-spacing:0.06em;color:#999;">Power</p>
                  <p style="margin:4px 0 0;font-size:15px;font-weight:700;color:#1a2e4a;"><?php echo esc_html( $power_fmt ); ?></p>
                </td>
              </tr>
            </table>
          </td>
        </tr>

        <!-- INQUIRY DETAILS LABEL -->
        <tr>
          <td style="padding:24px 32px 8px;">
            <p style="margin:0;font-size:11px;font-weight:700;text-transform:uppercase;letter-spacing:0.08em;color:#cc2129;">Inquiry Details</p>
          </td>
        </tr>

        <!-- CONTACT INFO -->
        <tr>
          <td style="padding:0 32px 12px;">
            <table width="100%" cellpadding="0" cellspacing="0">
              <tr>
                <td width="50%" style="padding:0 8px 12px 0;vertical-align:top;">
                  <table width="100%" cellpadding="12" style="background:#f7f7f7;border-radius:6px;border:1px solid #e5e5e5;">
                    <tr><td>
                      <p style="margin:0;font-size:10px;text-transform:uppercase;letter-spacing:0.06em;color:#999;">Name</p>
                      <p style="margin:4px 0 0;font-size:14px;font-weight:600;color:#1a2e4a;"><?php echo esc_html( $name ); ?></p>
                    </td></tr>
                  </table>
                </td>
                <td width="50%" style="padding:0 0 12px 8px;vertical-align:top;">
                  <table width="100%" cellpadding="12" style="background:#f7f7f7;border-radius:6px;border:1px solid #e5e5e5;">
                    <tr><td>
                      <p style="margin:0;font-size:10px;text-transform:uppercase;letter-spacing:0.06em;color:#999;">Phone</p>
                      <p style="margin:4px 0 0;font-size:14px;font-weight:600;color:#1a2e4a;"><?php echo $phone ? esc_html( $phone ) : '<span style="color:#bbb;">Not provided</span>'; ?></p>
                    </td></tr>
                  </table>
                </td>
              </tr>
              <tr>
                <td colspan="2" style="padding:0 0 12px;">
                  <table width="100%" cellpadding="12" style="background:#f7f7f7;border-radius:6px;border:1px solid #e5e5e5;">
                    <tr><td>
                      <p style="margin:0;font-size:10px;text-transform:uppercase;letter-spacing:0.06em;color:#999;">Email</p>
                      <p style="margin:4px 0 0;font-size:14px;font-weight:600;color:#1a2e4a;"><?php echo esc_html( $email ); ?></p>
                    </td></tr>
                  </table>
                </td>
              </tr>
            </table>
          </td>
        </tr>

        <!-- MESSAGE -->
        <tr>
          <td style="padding:0 32px 28px;">
            <table width="100%" cellpadding="16" style="background:#f7f7f7;border-left:4px solid #cc2129;border-radius:0 6px 6px 0;border-top:1px solid #e5e5e5;border-right:1px solid #e5e5e5;border-bottom:1px solid #e5e5e5;">
              <tr><td>
                <p style="margin:0 0 6px;font-size:10px;text-transform:uppercase;letter-spacing:0.06em;color:#999;">Message</p>
                <p style="margin:0;font-size:14px;color:#333;line-height:1.6;"><?php echo nl2br( esc_html( $message ) ); ?></p>
              </td></tr>
            </table>
          </td>
        </tr>

        <!-- CTA BUTTON -->
        <tr>
          <td style="padding:0 32px 32px;text-align:center;">
            <a href="<?php echo esc_url( $listing_url ); ?>" style="display:inline-block;background:#1a2e4a;color:#ffffff;text-decoration:none;font-size:14px;font-weight:700;padding:13px 32px;border-radius:6px;letter-spacing:0.02em;">View Listing on Website</a>
          </td>
        </tr>

        <!-- FOOTER -->
        <tr>
          <td style="background:#f0f0f0;padding:16px 32px;text-align:center;border-top:1px solid #e0e0e0;">
            <p style="margin:0;font-size:11px;color:#999;">This inquiry was submitted via tntcustommarine.com &nbsp;&bull;&nbsp; Sent from the TNT Marine Listings plugin by Cox Group</p>
          </td>
        </tr>

      </table>
    </td>
  </tr>
</table>
</body>
</html>
    <?php
    $html_body = ob_get_clean();

    $headers = [
        'Content-Type: text/html; charset=UTF-8',
        'From: TNT Custom Marine <inquiry@tntcustommarine.com>',
        'Reply-To: noreply@tntcustommarine.com',
    ];

    $sent = wp_mail( $to_email, $subject, $html_body, $headers );

    if ( $sent ) {
        wp_send_json_success( [ 'message' => 'Your inquiry has been sent. We will be in touch shortly.' ] );
    } else {
        wp_send_json_error( [ 'message' => 'There was a problem sending your inquiry. Please try again or call us directly.' ] );
    }
}
add_action( 'wp_ajax_tnt_marine_inquiry',        'tnt_marine_process_inquiry' );
add_action( 'wp_ajax_nopriv_tnt_marine_inquiry', 'tnt_marine_process_inquiry' );
