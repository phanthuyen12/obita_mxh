import re

with open("Content Generator - Master.yml", "r") as f:
    content = f.read()

# 1. First, restore final_answer_image answer back to original if it got half-replaced
content = content.replace("answer: '{{#image_tool.body#}}'", "answer: '{{#image_llm.text#}}'")
content = content.replace("id: final_answer_image\n      position: {x: 1250, y: 350}\n      positionAbsolute: {x: 1250, y: 350}", "id: final_answer_image\n      position: {x: 950, y: 350}\n      positionAbsolute: {x: 950, y: 350}")

# 2. Inject image_tool before final_answer_image
node_to_inject = """    - data:
        authorization:
          config: null
          type: no-auth
        body:
          data: |-
            {
              "model": "dall-e-3",
              "prompt": "{{#image_llm.text#}}",
              "n": 1,
              "size": "1024x1024"
            }
          type: json
        desc: Gọi API tạo ảnh (VD: OpenAI DALL-E)
        headers: |-
          Authorization: Bearer YOUR_API_KEY
          Content-Type: application/json
        method: post
        params: ''
        selected: false
        timeout:
          connect: 120
          read: 120
          write: 120
        title: API Tạo Ảnh DALL-E
        type: http-request
        url: https://api.openai.com/v1/images/generations
        variables: []
      height: 106
      width: 243
      id: image_tool
      position: {x: 950, y: 350}
      positionAbsolute: {x: 950, y: 350}
      selected: false
      type: custom
      zIndex: 0
"""

target = """    - data:
        answer: '{{#image_llm.text#}}'
        title: Trả lời Ảnh
        type: answer
        selected: false
      height: 104
      width: 243
      id: final_answer_image
      position: {x: 950, y: 350}
      positionAbsolute: {x: 950, y: 350}"""

replacement = node_to_inject + target.replace(
    "answer: '{{#image_llm.text#}}'", "answer: '{{#image_tool.body#}}'"
).replace(
    "position: {x: 950, y: 350}", "position: {x: 1250, y: 350}"
).replace(
    "positionAbsolute: {x: 950, y: 350}", "positionAbsolute: {x: 1250, y: 350}"
)

content = content.replace(target, replacement)

with open("Content Generator - Master.yml", "w") as f:
    f.write(content)
