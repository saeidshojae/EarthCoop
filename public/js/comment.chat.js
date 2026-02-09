let selectedCommentId = null;
let selectedCommentMessage = null;

function openCommentActions(commentId, profileUrl, content, likes = 0, dislikes = 0) {
  selectedCommentId = commentId;
  selectedCommentMessage = content; // اضافه شد ✅

  // نمایش محتوا
  document.getElementById('like-count-box').innerText = likes;
  document.getElementById('dislike-count-box').innerText = dislikes;

  // نمایش باکس
  document.getElementById('commentActionBox').style.display = 'block';
  document.getElementById('back').style.display = 'block';
//   document.querySelector('#profileLink').href = profileUrl

}

// AJAX واکنش
function reactToComment(type, selectedCommentId) {
  if (!selectedCommentId) return;

  $.ajax({
    url: `/comments/${selectedCommentId}/react`,
    method: 'POST',
    data: {
      _token:document.querySelector('meta[name="csrf-token"]').content,
      type: type
    },
    success: function (res) {
      if (res.status === 'success' || res.status === 'removed') {
        // بروزرسانی شمارنده‌ها
        document.querySelector('#like-count-' + res.id).innerHTML = 'لایک: ' + res.likes;
        document.querySelector('#dislike-count-' + res.id).innerHTML = 'دیسلاک: ' + res.dislikes;

        // هایلایت دکمه فعال یا حذف
        const likeBtn = document.querySelector(`#like-btn-${res.id}`);
        const dislikeBtn = document.querySelector(`#dislike-btn-${res.id}`);

        if (type === 'like') {
          if (res.status === 'removed') {
            likeBtn.classList.remove('active');
          } else {
            likeBtn.classList.add('active');
            dislikeBtn.classList.remove('active');
          }
        } else if (type === 'dislike') {
          if (res.status === 'removed') {
            dislikeBtn.classList.remove('active');
          } else {
            dislikeBtn.classList.add('active');
            likeBtn.classList.remove('active');
          }
        }
      } else {
        alert(res.message || 'خطا در ثبت واکنش.');
      }
    },
    error: function () {
      alert('خطا در ارتباط با سرور');
    }
  });
}

  
  function replyToSelectedComment(item) {
      let input = document.querySelector('input[name="message"]');
      selectedCommentMessage = document.querySelector('#msg-' + item + ' .comment-name').innerHTML
      input.placeholder = `↪️ پاسخ به: "${selectedCommentMessage}"\n`;
      document.querySelector('#parent_id').value=item
      input.focus();
      hidecommentActionBox()
  }

  function hidecommentActionBox(){
    document.querySelector('#back').style='display: none'

  }

  
function openGroupInfo() {
  document.getElementById('groupInfoPanel').style.right = '0';
}

function closeGroupInfo() {
  document.getElementById('groupInfoPanel').style.right = '-100%';
}


function openBlogBox(){
    document.querySelector('#back').style='display: block'
    document.querySelector('#postFormBox').style='display: block'
  }


  
  function cancelPostForm(){
    document.querySelector('#back').style='display: none'
    document.querySelector('#postFormBox').style='display: none'
  }

    window.addEventListener('DOMContentLoaded', function () {
    const chatBox = document.getElementById('chat-box');
    chatBox.scrollTop = chatBox.scrollHeight;
});
    

setInterval(function() {
  $.ajax({
        url: `/api/comments/${blogID}/messages`,
        method: 'GET',
        success: function(data) {
            $('#chat-box').html(data);

            const chatBox = document.getElementById('chat-box');
        },
        error: function() {
            console.error('❌ خطا در دریافت پیام‌ها');
        }
    });
}, 3000);


document.addEventListener('DOMContentLoaded', function () {
    const form = document.getElementById('chatForm');

    $('#chatForm').on('submit', function(e) {
  e.preventDefault();

  let formData = new FormData(this);
  let url = $(this).attr('action');

  $.ajax({
    url: url,
    type: 'POST',
    data: formData,
    processData: false, // برای فایل/فرم‌دیتا حتماً false باشه
    contentType: false, // همین‌طور 
    success: function(response) {
      if (response.status === 'success') {
        let msg = response.message;

        const chatBox = document.getElementById('chat-box');
        const newMsg = document.createElement('div');
        newMsg.classList.add('message-bubble', 'you');
        newMsg.setAttribute('id', 'msg-' + msg.id)
        
        let replyBox = '';
        if (msg.parent) {
          replyBox = `
            <a class="replay-box" href="#msg-${msg.parent.id}">
              <b style="margin: 0">${msg.parent.user_name}</b>
              <p style="margin: 0">${msg.parent.message}</p>
            </a>
          `;
        }
        
        newMsg.innerHTML = `
          ${replyBox}
          <p class='comment-name'>${msg.message}</p>
          <div class="time-react">
            <span class="time">${msg.created_at}</span>
          </div>
          <button class="btn btn-sm" onclick="replyToSelectedComment(${msg.id})">
            <i class="fa-solid fa-reply"></i>
          </button>
        `;
        
        chatBox.appendChild(newMsg);
        chatBox.scrollTop = chatBox.scrollHeight;

        

        let input = document.querySelector('input[name="message"]');
      input.placeholder ='نظر خود را بنویسید...'
      document.querySelector('#parent_id').value=''
        // ریست فرم
        $('#chatForm')[0].reset();
      } else {
        alert('خطا در ارسال پیام.');
      }
    },
    error: function(err) {
      console.error('خطا:', err);
      alert('ارسال پیام با مشکل مواجه شد.');
    }
  });
});

});