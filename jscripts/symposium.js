(function () {
  Symposium = {
    convid: null,
    lang: {
      responseMalformed: '',
      deleteConfirmation: '',
    },

    numberOfMessages: 0,

    pollMs: 5000,
    pollLimit: 50,
    isLastPage: true,

    _pollTimer: null,
    _pollInFlight: false,
    _sendInFlight: false,

    init: function () {
      Symposium.bindUI();
      Symposium.bindSendForm();
      Symposium.scrollToBottom(true);
      Symposium.startPolling();
    },

    bindUI: function () {
      $(document)
        .off('click.symposium', '.deleteMessages')
        .on('click.symposium', '.deleteMessages', function (e) {
          e.preventDefault();

          MyBB.prompt(Symposium.lang.deleteConfirmation, {
            buttons: [
              { title: yes_confirm, value: true },
              { title: no_confirm, value: false },
            ],
            submit: (ev, v) => {
              if (v === true) {
                Symposium.doDeleteMessages();
              }
            },
          });
        });

      $(document)
        .off('click.symposium', '.toggleDeleteMessages')
        .on('click.symposium', '.toggleDeleteMessages', function (e) {
          e.preventDefault();

          $('.delete').toggleClass('hidden');

          var checkboxes = $('[data-message-id].delete');
          if (checkboxes.is(':checked')) {
            checkboxes.prop('checked', false);
            $('[id^="pm-"], [id*="pm-"]').removeClass('highlighted');
          }
        });

      $(document)
        .off('change.symposium', '[data-message-id].delete')
        .on('change.symposium', '[data-message-id].delete', function () {
          $(this)
            .closest('[id^="pm-"], [id*="pm-"]')
            .toggleClass('highlighted');
        });
    },

    bindSendForm: function () {
      var form = $('form[name="input"][action="private.php"][method="post"]');
      if (!form.length) {
        return;
      }

      form.off('submit.symposium').on('submit.symposium', function (e) {
        e.preventDefault();
        Symposium.sendMessage($(this));
      });
    },

    isDeleteModeOn: function () {
      return !$('.delete.pm_alert').hasClass('hidden');
    },

    getLastPmid: function () {
      var last = $('#conversationContainer')
        .find('[id^="pm-"], [id*="pm-"]')
        .last();
      if (!last.length) {
        return 0;
      }
      var id = String(last.attr('id') || '');
      var m = id.match(/pm-(\d+)/);
      return m ? Number(m[1]) : 0;
    },

    scrollToBottom: function (force) {
      var d = $('#conversationContainer');
      if (!d.length) {
        return;
      }

      var nearBottom =
        d.prop('scrollHeight') - d.scrollTop() - d.outerHeight() < 160;

      if (force || nearBottom) {
        d.scrollTop(d.prop('scrollHeight'));
      }
    },

    appendMessagesHtml: function (html) {
      if (!html) {
        return;
      }

      var container = $('#conversationContainer');
      if (!container.length) {
        return;
      }

      var wasAtBottom =
        container.prop('scrollHeight') -
          container.scrollTop() -
          container.outerHeight() <
        160;

      if (container.find('[id^="pm-"], [id*="pm-"]').length === 0) {
        container.empty();
      }

      var nodes = $(html);

      if (Symposium.isDeleteModeOn()) {
        nodes.find('.delete.hidden').removeClass('hidden');
      } else {
        nodes.find('.delete').addClass('hidden');
      }

      container.append(nodes);
      Symposium.scrollToBottom(wasAtBottom);
    },

    getEditorContext: function (form) {
      var container = form.find('.sceditor-container').first();
      var textarea = form.find('textarea[name="message"]').first();

      if (!textarea.length && container.length) {
        textarea = container.find('textarea').first();
      }

      if (!container.length && textarea.length) {
        container = textarea.closest('.sceditor-container');
      }

      var inst = null;

      try {
        if (textarea.length && typeof textarea.sceditor === 'function') {
          inst = textarea.sceditor('instance') || null;
        }
      } catch (e) {}

      if (!inst && container.length) {
        var t2 = container.find('textarea').first();
        try {
          if (t2.length && typeof t2.sceditor === 'function') {
            inst = t2.sceditor('instance') || null;
          }
        } catch (e) {}
      }

      return { container: container, textarea: textarea, inst: inst };
    },

    getMessageValue: function (form) {
      var ctx = Symposium.getEditorContext(form);
      var message = '';

      try {
        if (ctx.inst && typeof ctx.inst.val === 'function') {
          message = String(ctx.inst.val() || '');
        }
      } catch (e) {}

      if (!message && ctx.textarea && ctx.textarea.length) {
        message = String(ctx.textarea.val() || '');
      }

      return $.trim(message);
    },

    clearMessageInput: function (form) {
      var ctx = Symposium.getEditorContext(form);

      if (ctx.inst) {
        try {
          if (typeof ctx.inst.val === 'function') {
            ctx.inst.val('');
          }
          if (typeof ctx.inst.updateOriginal === 'function') {
            ctx.inst.updateOriginal();
          }
          if (typeof ctx.inst.focus === 'function') {
            ctx.inst.focus();
          }
        } catch (e) {}
      }

      if (ctx.textarea && ctx.textarea.length) {
        ctx.textarea.val('');
      }

      if (ctx.container && ctx.container.length) {
        ctx.container.find('textarea').val('');

        var iframe = ctx.container.find('iframe').get(0);
        if (
          iframe &&
          iframe.contentWindow &&
          iframe.contentWindow.document &&
          iframe.contentWindow.document.body
        ) {
          try {
            iframe.contentWindow.document.body.innerHTML = '<p><br></p>';
          } catch (e) {}
        }
      }
    },

    sendMessage: function (form) {
      if (Symposium._sendInFlight) {
        return;
      }

      var message = Symposium.getMessageValue(form);

      if (!message) {
        return;
      }

      Symposium._sendInFlight = true;

      var submit = form.find('input[type="submit"], button[type="submit"]');
      submit.prop('disabled', true);

      var data = {
        action: 'symposium_send_message',
        convid: Symposium.convid,
        to: form.find('input[name="to"]').val(),
        subject: form.find('input[name="subject"]').val(),
        do: form.find('input[name="do"]').val(),
        pmid: form.find('input[name="pmid"]').val(),
        message: message,
        last_pmid: Symposium.getLastPmid(),
      };

      return Symposium.ajax.request(data, 'POST', (response) => {
        Symposium._sendInFlight = false;
        submit.prop('disabled', false);

        if (response && response.success) {
          if (response.pmid) {
            form.find('input[name="pmid"]').val(String(response.pmid));
          }

          Symposium.clearMessageInput(form);
          Symposium.appendMessagesHtml(response.html || '');
          Symposium.numberOfMessages = Math.max(
            1,
            (Symposium.numberOfMessages || 0) + 1,
          );
          Symposium.poll(true);
        }
      });
    },

    startPolling: function () {
      if (!Symposium.isLastPage) {
        return;
      }

      Symposium.stopPolling();

      Symposium._pollTimer = setInterval(function () {
        Symposium.poll(false);
      }, Symposium.pollMs);
    },

    stopPolling: function () {
      if (Symposium._pollTimer) {
        clearInterval(Symposium._pollTimer);
        Symposium._pollTimer = null;
      }
    },

    poll: function (force) {
      if (!Symposium.isLastPage) {
        return;
      }

      if (!force && document.hidden) {
        return;
      }

      if (Symposium._pollInFlight || Symposium._sendInFlight) {
        return;
      }

      Symposium._pollInFlight = true;

      var data = {
        action: 'symposium_fetch_messages',
        convid: Symposium.convid,
        last_pmid: Symposium.getLastPmid(),
        limit: Symposium.pollLimit,
      };

      return Symposium.ajax.request(data, 'POST', (response) => {
        Symposium._pollInFlight = false;

        if (response && response.success && response.html) {
          Symposium.appendMessagesHtml(response.html);
        }
      });
    },

    doDeleteMessages: function () {
      var toDelete = [];

      $.each($('[data-message-id].delete:checked'), function () {
        var pmid = Number($(this).data('message-id'));
        if (pmid) {
          toDelete.push(pmid);
        }
      });

      var data = {
        action: 'symposium_delete_pms',
        pmids: toDelete,
        convid: Symposium.convid,
      };

      return Symposium.ajax.request(data, 'POST', (response) => {
        if (response.success) {
          $.each(toDelete, function (k, v) {
            $('#pm-' + v).remove();
          });
        }
      });
    },

    ajax: {
      request: function (data, type, callback) {
        if (type === 'POST') {
          data.my_post_key = my_post_key;
        }

        return $.ajax({
          type: type,
          url: 'xmlhttp.php',
          data: data,
          complete: (xhr) => {
            var response;

            try {
              response = $.parseJSON(xhr.responseText);
            } catch (e) {
              console.log(e);
              $.jGrowl(Symposium.lang.responseMalformed, {
                theme: 'jgrowl_error',
              });

              if (typeof callback === 'function') {
                callback({
                  success: false,
                  errors: [Symposium.lang.responseMalformed],
                });
              }

              return;
            }

            if (response.errors) {
              $.each(response.errors, (index, msg) => {
                $.jGrowl(msg, { theme: 'jgrowl_error' });
              });
            } else if (response.message) {
              $.jGrowl(response.message, { theme: 'jgrowl_success' });
            }

            if (typeof callback === 'function') {
              callback(response);
            }
          },
        });
      },
    },
  };
})();
