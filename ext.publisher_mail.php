<?php if (!defined('BASEPATH')) exit('No direct script access allowed');

class Publisher_mail_ext
{
    var $name = 'Publisher Mail';
    var $version = '1.0';
    var $description = 'Customize emails for Publisher actions';
    var $settings_exist = 'y';
    var $docs_url = '';

    var $settings = array();

    /**
     * Constructor
     *
     * @param   mixed   Settings array or empty string if none exist.
     */

    function __construct($settings = '')
    {
        $this->settings = $settings;

        // Load to send custom email messages
        ee()->load->library('email');
    }

    function settings()
    {
        $settings = array();

        $settings['send_approval_email'] = array('r', array('y' => "Yes", 'n' => "No"), 'y');
        $settings['approval_subject'] = array('i', '', "Your content has been approved.");
        $settings['approval_message'] = array('t', array('rows' => '10'), '');

        $settings['send_denial_email'] = array('r', array('y' => "Yes", 'n' => "No"), 'y');
        $settings['denial_subject'] = array('i', '', "Your content needs editing.");
        $settings['denial_message'] = array('t', array('rows' => '10'), '');

        return $settings;
    }

    /**
     * Activate Extension
     *
     * This function enters the extension into the exp_extensions table
     *
     * @see http://ellislab.com/codeigniter/user-guide/database/index.html for
     * more information on the db class.
     *
     * @return void
     */
    function activate_extension()
    {
        $ext_template = array(
            'class' => __CLASS__,
            'settings' => '',
            'version' => $this->version,
            'enabled' => 'y'
        );

        $extensions = array(
            array('hook' => 'publisher_entry_save_end', 'method' => 'entry_save_end', 'priority' => 1),
            array('hook' => 'publisher_send_email', 'method' => 'send_email', 'priority' => 1)
        );

        foreach ($extensions as $extension) {
            ee()->db->insert('extensions', array_merge($ext_template, $extension));
        }
    }

    /**
     * Disable Extension
     *
     * This method removes information from the exp_extensions table
     *
     * @return void
     */
    function disable_extension()
    {
        ee()->db->where('class', __CLASS__);
        ee()->db->delete('extensions');
    }

    /**
     * Send approval email
     */
    function entry_save_end($entry_id, $meta_data, $post_data)
    {
        if ($this->settings['send_approval_email'] == 'y' && isset($post_data['publisher_view_status']) && $post_data['publisher_view_status'] == 'draft' && isset($post_data['publisher_save_status']) && $post_data['publisher_save_status'] == 'open') {

            // Send an email that it has been approved
            ee()->db->select('screen_name, email');
            ee()->db->from('exp_members');
            ee()->db->join('exp_channel_titles', 'exp_channel_titles.author_id = exp_members.member_id');
            ee()->db->where('entry_id', $entry_id);

            $query = ee()->db->get();

            if ($query->num_rows() > 0) {

                $row = $query->row_array(0);
                $data = array(
                    'title' => $meta_data['title'],
                    'url_title' => $meta_data['url_title']
                );
                $tags[] = array_merge($data, $row);

                $template = ee()->TMPL->parse_variables($this->settings['approval_message'], $tags);

                ee()->email->mailtype = 'html';

                foreach ($tags as $tag) {
                    ee()->email->to($tag['email']);
                    ee()->email->from(ee()->session->userdata['email'], ee()->session->userdata['screen_name']);
                    ee()->email->subject($this->settings['approval_subject']);
                    ee()->email->message(nl2br($template));
                    ee()->email->set_alt_message(strip_tags($template));
                    ee()->email->send();
                }
            }
            return TRUE;
        }
    }

    /**
     * Custom denial email
     */
    function send_email($to, $subject, $message, $data)
    {
        if ($this->settings['send_denial_email'] == 'y') {

            // Get the post entry ID
            $entry_id = ee()->input->post('type_id', TRUE);

            ee()->db->select('title, url_title, screen_name, email ');
            ee()->db->from('exp_channel_titles');
            ee()->db->join('exp_members', 'exp_members.member_id = exp_channel_titles.author_id');
            ee()->db->where('entry_id', $entry_id);

            $query = ee()->db->get();

            if ($query->num_rows() > 0) {

                $tags[] = $query->row_array(0);

                $comments = $this->settings['denial_message'] . "\n" . $message;

                $template = ee()->TMPL->parse_variables($comments, $tags);

                foreach ($to as $address) {
                    ee()->email->to($address);
                    ee()->email->from($data['reply_to'], $data['reply_name']);
                    ee()->email->subject($this->settings['denial_subject']);
                    ee()->email->message(nl2br($template));
                    ee()->email->set_alt_message(strip_tags($template));
                    ee()->email->send();
                }
            }
        }

        return FALSE;
    }

}
