import re

with open("Content Generator - Master.yml", "r") as f:
    content = f.read()

# 1. Update the edges to point to image_tool
content = content.replace('''    - data:
        isInIteration: false
        isInLoop: false
        sourceType: llm
        targetType: answer
      id: image_llm-answer
      selected: false
      source: image_llm
      sourceHandle: source
      target: final_answer_image
      targetHandle: target
      type: custom
      zIndex: 0''', '''    - data:
        isInIteration: false
        isInLoop: false
        sourceType: llm
        targetType: http-request
      id: image_llm-image_tool
      selected: false
      source: image_llm
      sourceHandle: source
      target: image_tool
      targetHandle: target
      type: custom
      zIndex: 0
    - data:
        isInIteration: false
        isInLoop: false
        sourceType: http-request
        targetType: answer
      id: image_tool-answer
      selected: false
      source: image_tool
      sourceHandle: source
      target: final_answer_image
      targetHandle: target
      type: custom
      zIndex: 0''')

# 2. Add the image_tool node before the final_answer_image node
node_str = '''      type: custom
      zIndex: 0
    - data:
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
    - data:
        answer: '{{#image_tool.body#}}'
        title: Trả lời Ảnh'''

content = content.replace('''      type: custom
      zIndex: 0
    - data:
        answer: '{{#image_llm.text#}}'
        title: Trả lời Ảnh''', node_str)

# 3. Update the final_answer_image positions
content = content.replace('''      id: final_answer_image
      position: {x: 950, y: 350}
      positionAbsolute: {x: 950, y: 350}''', '''      id: final_answer_image
      position: {x: 1250, y: 350}
      positionAbsolute: {x: 1250, y: 350}''')

with open("Content Generator - Master.yml", "w") as f:
    f.write(content)
