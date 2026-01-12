pipeline {
    agent none

    triggers {
        githubPush()
        pollSCM('H/5 * * * *')
    }

    environment {
        DOCKER_IMAGE = 'edukativa/moodle-cognus'
        DOCKER_HUB_CREDS = credentials('docker-hub-credentials')
        APP_PORT = '8008'
        APP_PORT_ALT = '8009'
        MOODLEDATA_PATH = '/var/www/cognus.edukativa.com.br/moodle-data'
    }

    stages {
        stage('Checkout') {
            agent any
            steps {
                checkout scm
            }
        }

        stage('Build & Publish Docker Image') {
            agent { label 'docker-build' }
            when {
                branch 'main'
                not {
                    changelog '.*\\[skip ci\\].*'
                }
            }
            steps {
                script {
                    echo "🐳 Building and publishing Docker image..."
                    
                    // Login no Docker Hub
                    sh "echo ${DOCKER_HUB_CREDS_PSW} | docker login -u ${DOCKER_HUB_CREDS_USR} --password-stdin"
                    
                    // Gerar tags
                    def buildDate = sh(script: "date +'%Y%m%d%H%M'", returnStdout: true).trim()
                    def shortSha = sh(script: "git rev-parse --short HEAD", returnStdout: true).trim()
                    def imageTag = "${buildDate}-${shortSha}"
                    
                    echo "📋 Building with tags: ${imageTag}, latest"
                    
                    // Build e push da imagem
                    sh """
                        docker build -t ${DOCKER_IMAGE}:${imageTag} .
                        docker tag ${DOCKER_IMAGE}:${imageTag} ${DOCKER_IMAGE}:latest
                        
                        docker push ${DOCKER_IMAGE}:${imageTag}
                        docker push ${DOCKER_IMAGE}:latest
                    """
                    
                    // Salvar tag para próximo stage
                    env.IMAGE_TAG = imageTag
                }
            }
        }

        stage('Deploy to Production') {
            agent { label 'edukativa-server' }
            when {
                branch 'main'
                not {
                    changelog '.*\\[skip ci\\].*'
                }
            }
            steps {
                script {
                    echo "🚀 Deploying to production..."
                    
                    // Login no Docker Hub
                    sh "echo ${DOCKER_HUB_CREDS_PSW} | sudo docker login -u ${DOCKER_HUB_CREDS_USR} --password-stdin"
                    
                    // Pull da imagem
                    sh "sudo docker pull ${DOCKER_IMAGE}:latest"
                    
                    // Parar e remover container existente
                    sh """
                        sudo docker stop moodle-cognus || true
                        sudo docker rm moodle-cognus || true
                    """
                    
                    // Verificar porta disponível
                    def port = sh(script: """
                        if sudo lsof -i :${APP_PORT} > /dev/null; then
                            echo "Port ${APP_PORT} in use, using ${APP_PORT_ALT}"
                            echo ${APP_PORT_ALT}
                        else
                            echo ${APP_PORT}
                        fi
                    """, returnStdout: true).trim()
                    
                    echo "🔌 Using port: ${port}"
                    
                    // Verificar se moodledata existe
                    sh """
                        if [ ! -d "${MOODLEDATA_PATH}" ]; then
                            echo "⚠️ Creating moodledata directory: ${MOODLEDATA_PATH}"
                            sudo mkdir -p ${MOODLEDATA_PATH}
                            sudo chown -R www-data:www-data ${MOODLEDATA_PATH}
                            sudo chmod -R 775 ${MOODLEDATA_PATH}
                        fi
                    """
                    
                    // Iniciar container
                    sh """
                        sudo docker run -d \\
                            --name moodle-cognus \\
                            --restart unless-stopped \\
                            -p ${port}:80 \\
                            -v ${MOODLEDATA_PATH}:/var/www/moodledata \\
                            ${DOCKER_IMAGE}:latest
                    """
                    
                    // Salvar porta usada para verificação
                    env.DEPLOYED_PORT = port
                    
                    // Aguardar container iniciar
                    sh "sleep 10"
                    
                    // Verificar se está rodando
                    sh "sudo docker ps | grep moodle-cognus"
                    
                    echo "✅ Deployment successful on port ${port}!"
                    echo "📝 Configure seu Nginx para fazer proxy_pass para localhost:${port}"
                }
            }
        }

        stage('Verify Deployment') {
            agent { label 'edukativa-server' }
            when {
                branch 'main'
                not {
                    changelog '.*\\[skip ci\\].*'
                }
            }
            steps {
                script {
                    echo "🔍 Verifying deployment..."
                    
                    sh """
                        echo "Container status:"
                        sudo docker ps --filter name=moodle-cognus --format 'table {{.ID}}\\t{{.Status}}\\t{{.Ports}}\\t{{.Image}}'
                        
                        echo "\\nContainer logs (last 30 lines):"
                        sudo docker logs moodle-cognus --tail 30
                        
                        echo "\\nMoodledata volume:"
                        sudo docker inspect moodle-cognus | grep -A 5 Mounts
                        
                        echo "\\nHealthcheck:"
                        curl -f http://localhost:${DEPLOYED_PORT}/ -I || echo "⚠️ Service not responding yet"
                    """
                }
            }
        }
    }

    post {
        always {
            script {
                // Cleanup apenas nos agentes que fizeram build
                if (env.STAGE_NAME == 'Build & Publish Docker Image') {
                    sh """
                        docker logout || true
                        docker image prune -f || true
                    """
                }
                
                // Cleanup no servidor de produção
                if (env.STAGE_NAME == 'Deploy to Production' || env.STAGE_NAME == 'Verify Deployment') {
                    sh "sudo docker logout || true"
                }
            }
        }
        success {
            echo "✅ Pipeline completed successfully!"
            echo "🌐 Moodle disponível na porta ${env.DEPLOYED_PORT ?: APP_PORT}"
        }
        failure {
            echo "❌ Pipeline failed!"
        }
    }
}