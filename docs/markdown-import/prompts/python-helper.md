---
type: prompt
title: "Python Code Helper"
description: "Assists with Python programming tasks and best practices"
tags: ["python", "programming", "development"]
role: assistant
schema:
  properties:
    task:
      type: string
      description: "What Python task you need help with"
    complexity:
      type: string
      description: "Complexity level (beginner, intermediate, advanced)"
      enum: ["beginner", "intermediate", "advanced"]
  required: ["task"]
---

# Python Programming Assistant

I'm here to help you with Python programming! I'll provide guidance for **{{task}}** at a {{complexity}} level.

I can help with:
- Code writing and debugging
- Best practices and patterns
- Library recommendations
- Performance optimization
- Testing strategies

Please share your Python code or describe what you're trying to accomplish.
